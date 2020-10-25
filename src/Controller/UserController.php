<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


/**
 * @property  userPasswordEncoder
 */
class UserController extends AbstractFOSRestController
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var UserPasswordEncoderInterface
     */
    private $userPasswordEncoder;

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
        $this->entityManager = $entityManager;
        $this->userPasswordEncoder = $userPasswordEncoder;
    }

    /**
     * @Rest\Get("api/user/{id}")
     * @param int $id
     * @return \FOS\RestBundle\View\View
     */
    public function userGet(int $id)
    {
        $user = $this->userRepository->findOneBy([
            'id' => $id
        ]);
        if (!isset($user)) {
            return $this->view([
                'message' => 'This user does not exist!'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->view([
            $user
        ], Response::HTTP_OK
        );
    }

    /**
     * @Rest\Patch("api/user/{id}")
     * @Rest\RequestParam(name="password", description="password", nullable=false)
     * @param ParamFetcher $paramFetcher
     * @return \FOS\RestBundle\View\View
     */
    public function userPatch(ParamFetcher $paramFetcher, int $id)
    {
        $user = $this->userRepository->findOneBy([
            'id' => $id
        ]);
        if (!isset($user)) {
            return $this->view([
                'message' => 'This user does not exist!'
            ], Response::HTTP_NOT_FOUND);
        }
        // todo: more sophisticated requirements - regex validation of password
        // and check also against old password
        $password  = $paramFetcher->get('password');
        if (isset($password)) {
            $user->setPassword(
                $this->userPasswordEncoder->encodePassword($user, $password)
            );
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            return $this->view([
                'message' => 'Password was successfully changed!',
            ], Response::HTTP_OK
            );
        }
    }


}
