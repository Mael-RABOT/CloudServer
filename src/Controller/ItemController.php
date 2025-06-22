<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ItemRepository $itemRepository,
        private SluggerInterface $slugger
    ) {}

    public function upload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $location = $request->request->get('location');
        $file = $request->files->get('content');

        if (!$location || !$file) {
            return new JsonResponse(['error' => 'Location and content file are required'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Invalid file upload'], Response::HTTP_BAD_REQUEST);
        }

        // Validate file type
        $mimeType = $file->getMimeType();

        // Allow any text-based files, images, and PDFs
        $allowedTypes = [
            'application/pdf',
            'application/json',
            'application/xml',
            'application/javascript'
        ];

        $allowedImageTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
            'image/tiff',
            'image/x-icon'
        ];

        // Check if it's a text file (starts with 'text/')
        $isTextFile = str_starts_with($mimeType, 'text/');
        $isAllowedType = in_array($mimeType, $allowedTypes);
        $isImage = in_array($mimeType, $allowedImageTypes);

        if (!$isTextFile && !$isAllowedType && !$isImage) {
            return new JsonResponse(['error' => 'Invalid file type. Only text files, images, and PDF files are allowed'], Response::HTTP_BAD_REQUEST);
        }

        // Create upload directory for user
        $uploadDir = $this->getParameter('kernel.project_dir') . "/var/upload/{$user->getUsername()}";
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return new JsonResponse([
                    'error' => 'Failed to create upload directory',
                    'path' => $uploadDir,
                    'current_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'unknown',
                    'permissions' => is_dir(dirname($uploadDir)) ? substr(sprintf('%o', fileperms(dirname($uploadDir))), -4) : 'not_exists'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Ensure directory is writable
        if (!is_writable($uploadDir)) {
            return new JsonResponse([
                'error' => 'Upload directory is not writable',
                'path' => $uploadDir,
                'permissions' => substr(sprintf('%o', fileperms($uploadDir)), -4)
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Generate unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        // Move file to upload directory
        try {
            $file->move($uploadDir, $newFilename);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to save file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Create Item entity
        $item = new Item();
        $item->setUser($user);
        $item->setLocation($location);
        $item->setFilename($newFilename);
        $item->setContentType($mimeType);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $item->getId(),
            'location' => $item->getLocation(),
            'filename' => $item->getFilename(),
            'contentType' => $item->getContentType(),
            'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s')
        ], Response::HTTP_CREATED);
    }

    public function download(Item $item): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user || $item->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filePath = $this->getParameter('kernel.project_dir') . "/var/upload/{$item->getUser()->getUsername()}/{$item->getFilename()}";

        if (!file_exists($filePath)) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $item->getFilename()
        );
        $response->headers->set('Content-Type', $item->getContentType());

        return $response;
    }

    public function myItems(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $items = $this->itemRepository->findByUser($user);

        $data = array_map(function(Item $item) {
            return [
                'id' => $item->getId(),
                'location' => $item->getLocation(),
                'filename' => $item->getFilename(),
                'contentType' => $item->getContentType(),
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $items);

        return new JsonResponse($data);
    }
}