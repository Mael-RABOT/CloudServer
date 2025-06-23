<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Item;
use App\Repository\ItemRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ItemDataProvider implements ProviderInterface
{
    public function __construct(
        private ItemRepository $itemRepository,
        private Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('User not authenticated');
        }

        // Handle individual item operations (GET, PUT, DELETE)
        if (isset($uriVariables['id'])) {
            $item = $this->itemRepository->find($uriVariables['id']);

            if (!$item) {
                throw new NotFoundHttpException('Item not found');
            }

            if ($item->getUser()->getId() !== $user->getId()) {
                throw new AccessDeniedHttpException('Access denied');
            }

            return $item;
        }

        // Handle collection operations
        return $this->itemRepository->findByUser($user);
    }
}