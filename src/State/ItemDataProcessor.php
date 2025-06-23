<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ItemDataProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ParameterBagInterface $parameterBag
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Item
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('User not authenticated');
        }

        // For PUT operations, we need to handle text file content updates
        if ($operation->getMethod() === 'PUT') {
            return $this->handlePutOperation($data, $uriVariables['id'], $user);
        }

        // For other operations, just persist the data
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    private function handlePutOperation(array $data, int $itemId, $user): Item
    {
        // Find the existing item
        $item = $this->entityManager->getRepository(Item::class)->find($itemId);

        if (!$item) {
            throw new \InvalidArgumentException('Item not found');
        }

        if ($item->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Access denied');
        }

        // Check if this is a text file
        if (!str_starts_with($item->getContentType(), 'text/')) {
            throw new BadRequestHttpException('Only text files can be updated');
        }

        // Update filename if provided
        if (isset($data['filename'])) {
            $item->setFilename($data['filename']);
        }

        // Update content if provided
        if (isset($data['content'])) {
            $this->updateFileContent($item, $data['content']);
        }

        $this->entityManager->flush();

        return $item;
    }

    private function updateFileContent(Item $item, string $content): void
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $filePath = $projectDir . "/var/upload/{$item->getUser()->getUsername()}/{$item->getFilename()}";

        if (!file_exists($filePath)) {
            throw new \RuntimeException('File not found on disk');
        }

        // Write new content to file
        if (file_put_contents($filePath, $content) === false) {
            throw new \RuntimeException('Failed to write content to file');
        }
    }
}