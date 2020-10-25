<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegistrationController extends AbstractFOSRestController
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var UserPasswordEncoderInterface
     */
    private $userPasswordEncoder;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * RegistrationController constructor.
     *
     * @param UserRepository               $userRepository
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @param EntityManagerInterface       $entityManager
     */
    public function __construct(UserRepository $userRepository, UserPasswordEncoderInterface $userPasswordEncoder, EntityManagerInterface $entityManager)
    {
        $this->userRepository = $userRepository;
        $this->userPasswordEncoder = $userPasswordEncoder;
        $this->entityManager = $entityManager;
    }

    /**
     * @Rest\Post("api/register", name="user_register")
     * @param ParamFetcher $paramFetcher
     * @Rest\RequestParam(name="email", description="email", nullable=false)
     * @Rest\RequestParam(name="password", description="password", nullable=false)
     * @Rest\RequestParam(name="firstname", description="first name", nullable=false)
     * @Rest\RequestParam(name="lastname", description="last name", nullable=false)
     * @param ParamFetcher $paramFetcher
     *
     * @return \FOS\RestBundle\View\View
     */
    public function index(ParamFetcher $paramFetcher)
    {
        $email = $paramFetcher->get('email');
        $password = $paramFetcher->get('password');
        $firstName = $paramFetcher->get('firstname');
        $lastName = $paramFetcher->get('lastname');

        $user = $this->userRepository->findOneBy([
            'email' => $email
        ]);
        if (isset($user)) {
            return $this->view([
                'message' => 'This user already exists'
            ], Response::HTTP_CONFLICT);

        }
        $user = new User();
        $user->setPassword(
            $this->userPasswordEncoder->encodePassword($user, $password)
        );
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $this->view([
            'message' => 'User was successfully created',
            $user
        ], Response::HTTP_CREATED
        );
    }
}
