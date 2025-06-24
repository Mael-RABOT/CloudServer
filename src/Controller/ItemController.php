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
            'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
            'isShared' => $item->isShared()
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
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'isShared' => $item->isShared()
            ];
        }, $items);

        return new JsonResponse($data);
    }

    public function updateTextFile(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Find the item by ID
        $item = $this->itemRepository->find($id);

        if (!$item) {
            return new JsonResponse(['error' => 'Item not found'], Response::HTTP_NOT_FOUND);
        }

        if ($item->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Check if this is a text file
        if (!str_starts_with($item->getContentType(), 'text/')) {
            return new JsonResponse(['error' => 'Only text files can be updated'], Response::HTTP_BAD_REQUEST);
        }

        // Get request content
        $content = json_decode($request->getContent(), true);

        if (!$content) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . "/var/upload/{$user->getUsername()}";
        $currentFilePath = $uploadDir . "/{$item->getFilename()}";

        // Check if the current file exists
        if (!file_exists($currentFilePath)) {
            return new JsonResponse(['error' => 'File not found on disk'], Response::HTTP_NOT_FOUND);
        }

        // Handle filename change first
        if (isset($content['filename']) && $content['filename'] !== $item->getFilename()) {
            $newFilePath = $uploadDir . "/{$content['filename']}";

            // Rename the file
            if (!rename($currentFilePath, $newFilePath)) {
                return new JsonResponse(['error' => 'Failed to rename file'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Update the file path for content update
            $currentFilePath = $newFilePath;
            $item->setFilename($content['filename']);
        }

        // Update content if provided
        if (isset($content['content'])) {
            // Ensure the file is writable
            if (!is_writable($currentFilePath)) {
                return new JsonResponse([
                    'error' => 'File is not writable',
                    'file_path' => $currentFilePath,
                    'permissions' => substr(sprintf('%o', fileperms($currentFilePath)), -4)
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Write new content to file
            $bytesWritten = file_put_contents($currentFilePath, $content['content']);
            if ($bytesWritten === false) {
                return new JsonResponse([
                    'error' => 'Failed to write content to file',
                    'file_path' => $currentFilePath,
                    'content_length' => strlen($content['content']),
                    'file_exists' => file_exists($currentFilePath),
                    'file_writable' => is_writable($currentFilePath)
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $item->getId(),
            'location' => $item->getLocation(),
            'filename' => $item->getFilename(),
            'contentType' => $item->getContentType(),
            'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
            'content_updated' => isset($content['content']),
            'filename_updated' => isset($content['filename'])
        ]);
    }

    public function deleteItem(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Find the item by ID
        $item = $this->itemRepository->find($id);

        if (!$item) {
            return new JsonResponse(['error' => 'Item not found'], Response::HTTP_NOT_FOUND);
        }

        if ($item->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . "/var/upload/{$user->getUsername()}";
        $filePath = $uploadDir . "/{$item->getFilename()}";

        // Delete the file from filesystem if it exists
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                return new JsonResponse(['error' => 'Failed to delete file from filesystem'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Delete the item from database
        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Item deleted successfully'], Response::HTTP_NO_CONTENT);
    }

    public function getSharedUrl(Item $item): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user || $item->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        if (!$item->isShared()) {
            return new JsonResponse(['error' => 'Item is not shared'], Response::HTTP_BAD_REQUEST);
        }

        // Generate the shared URL
        $sharedUrl = $this->generateUrl('app_item_shared_download', ['id' => $item->getId()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse([
            'shared_url' => $sharedUrl,
            'item_id' => $item->getId(),
            'filename' => $item->getFilename(),
            'content_type' => $item->getContentType()
        ]);
    }

    public function toggleShared(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Find the item by ID
        $item = $this->itemRepository->find($id);

        if (!$item) {
            return new JsonResponse(['error' => 'Item not found'], Response::HTTP_NOT_FOUND);
        }

        if ($item->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Toggle the shared status
        $item->setIsShared(!$item->isShared());
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $item->getId(),
            'is_shared' => $item->isShared(),
            'message' => $item->isShared() ? 'Item is now shared' : 'Item is no longer shared'
        ]);
    }

    public function sharedDownload(Item $item): Response
    {
        if (!$item->isShared()) {
            return new JsonResponse(['error' => 'Item is not shared'], Response::HTTP_FORBIDDEN);
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
}