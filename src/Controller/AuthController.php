<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        return $this->json([
            'message' => 'Registration is disabled, please contact the administrator.',
            'status' => 'error',
            'code' => 403,
            'timestamp' => (new \DateTime())->format('c'),
        ]);

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['username']) || !isset($data['password']) || !isset($data['email'])) {
            return $this->json([
                'error' => 'Missing required fields: username, password, email'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already exists
        $existingUser = $entityManager->getRepository(User::class)->findOneBy([
            'username' => $data['username']
        ]);

        if ($existingUser) {
            return $this->json([
                'error' => 'Username already exists'
            ], Response::HTTP_CONFLICT);
        }

        $existingEmail = $entityManager->getRepository(User::class)->findOneBy([
            'email' => $data['email']
        ]);

        if ($existingEmail) {
            return $this->json([
                'error' => 'Email already exists'
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles(['ROLE_USER']);

        // Validate the user entity
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'error' => 'Validation failed',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail()
            ]
        ], Response::HTTP_CREATED);
    }

    public function login(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // This method should not be called directly when using json_login
        // The security system will handle the authentication
        return $this->json([
            'error' => 'This endpoint should be accessed through the security system'
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }
}