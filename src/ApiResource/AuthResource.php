<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            name: 'auth_register',
            description: 'Register a new user',
            controller: 'App\Controller\AuthController::register'
        ),
        new Post(
            uriTemplate: '/auth/login',
            name: 'auth_login',
            description: 'Login user and get JWT token',
            controller: 'App\Controller\AuthController::login'
        )
    ]
)]
class AuthResource
{
    #[Assert\NotBlank]
    public string $username;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    public string $password;
}