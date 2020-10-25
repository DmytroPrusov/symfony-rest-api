<?php
/**
 * Created by PhpStorm.
 * User: dmytro
 * Date: 24.10.20
 * Time: 21:09
 */

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\View\View;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ProductController extends AbstractFOSRestController
{

    /**
     * @var ProductRepository
     */
    private $productRepository;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(ProductRepository $productRepository, EntityManagerInterface $entityManager)
    {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @return View
     */
    public function getProductsAction()
    {
        $products = $this->productRepository->findAll();

        return $this->view($products, Response::HTTP_OK);
    }

    /**
     * @param int $id
     *
     * @return \FOS\RestBundle\View\View
     */
    public function getProductAction(int $id, UserPasswordEncoderInterface $encoder)
    {
        $data = $this->productRepository->findOneBy(['id' => $id]);
        if (!isset($data)) {
            return $this->productDoesNotExist($id);
        }
        return $this->view($data, Response::HTTP_OK);
    }

    /**
     * @Rest\RequestParam(name="title", description="Title of the Product")
     * @Rest\RequestParam(name="details", description="Details of the Product")
     * @Rest\RequestParam(name="isPremium", description="Check whether product is premium")
     * @param ParamFetcher $paramFetcher
     *
     * @return \FOS\RestBundle\View\View
     */
    public function postProductsAction(ParamFetcher $paramFetcher)
    {

        $title = $paramFetcher->get('title');
        $details = $paramFetcher->get('details');
        $isPremium = $paramFetcher->get('isPremium');

        if (isset($title) and isset($details) and isset($isPremium)) {
            $product = new Product();
            $product->setTitle($title);
            $product->setDetails($details);
            $product->setIsPremium($isPremium);
            $this->entityManager->persist($product);
            $this->entityManager->flush();

            return $this->view($product, Response::HTTP_CREATED);
        }
        return $this->view(
            [
                'message ' => 'A request was wrong!'
            ],
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * @Rest\RequestParam(name="title", description="Title of the Product", nullable=true)
     * @Rest\RequestParam(name="details", description="Details of the Product", nullable=true)
     * @Rest\RequestParam(name="isPremium", description="Check whether product is premium", nullable=true)
     * @param ParamFetcher $paramFetcher
     *
     * @param int          $id
     *
     * @return \FOS\RestBundle\View\View
     */
    public function putProductsAction(ParamFetcher $paramFetcher, int $id)
    {
        $product =  $this->productRepository->findOneBy(['id' => $id]);
        if (!isset($product)) {
            return $this->productDoesNotExist($id);
        }
        $title = $paramFetcher->get('title');
        $details = $paramFetcher->get('details');
        $isPremium = $paramFetcher->get('isPremium');
        if (isset($title) or isset($details) or isset($isPremium)) {
            if (isset($title))
                $product->setTitle($title);
            if (isset($details))
                $product->setDetails($details);
            if (isset($isPremium))
                $product->setIsPremium($isPremium);

            $this->entityManager->persist($product);
            $this->entityManager->flush();
            return $this->view($product, Response::HTTP_OK);
        }
        return $this->view(
            [
                'message ' => 'A request was wrong!'
            ],
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * @param int $id
     *
     * @return \FOS\RestBundle\View\View
     */
    public function deleteProductsAction(int $id)
    {
        $product =  $this->productRepository->findOneBy(['id' => $id]);
        if (!isset($product)) {
            return $this->productDoesNotExist($id);
        }
        // at first we need to delete image attached to the product:
        $currentImage  = $product->getImage();
        if (isset($currentImage)) {
            $isDeletedResult = $this->deleteImage($currentImage);
            if ($isDeletedResult instanceof View) {
                return $isDeletedResult;
            }
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();
        return $this->view([
            'message' => sprintf('Product with id {%s} was successfully deleted', $id)
        ]);
    }

    /**
     * @Rest\FileParam(name="image",description="Image of the product", nullable=false, image=true)
     * @param ParamFetcher $paramFetcher
     * @param int          $id
     *
     */
    public function postProductsImageAction(ParamFetcher $paramFetcher, int $id)
    {
        $product =  $this->productRepository->findOneBy(['id' => $id]);
        if (!isset($product)) {
            return $this->productDoesNotExist($id);
        }
        $currentImage  = $product->getImage();
        if (isset($currentImage)) {
            $isDeletedResult = $this->deleteImage($currentImage);
            if ($isDeletedResult instanceof View) {
                return $isDeletedResult;
            }
        }
        /** @var UploadedFile $file */
        $file = $paramFetcher->get('image');
        if ($file) {
            $filename = md5(uniqid()) . '.' . $file->guessClientExtension();
            try {
                $file->move(
                    $this->getParameter('uploads_dir'),
                    $filename
                );
            } catch (FileException $e) {
                return $this->view(
                    [
                        'message' => sprintf('Problems were encountered while uploading a file  (%s), pls contact a support team!', $e->getMessage()),
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            $product->setImage($filename);
            $this->entityManager->persist($product);
            $this->entityManager->flush();
            return $this->view(
                [
                    'message' => sprintf("An image for a product with id {%d} was successfully uploaded!", $id)
                ],
                Response::HTTP_OK
            );
        }

        return $this->view(
            [
                'message' =>'Problems were encountered while uploading a file, pls contact a support team!'
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    private function productDoesNotExist ($id) {

        return $this->view(
            [
                'message' => sprintf("A product with id {%d} does not exist!", $id)
            ],
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * @param string $imageName
     *
     * @return \FOS\RestBundle\View\View|boolean
     */
    private  function deleteImage(string $imageName)  {

        $filesystem = new Filesystem();
        try {
            $filesystem->remove(
                $this->getParameter('uploads_dir') . $imageName
            );
        } catch (FileException $e) {
            return $this->view(
                [
                    'message' => sprintf('Problems were encountered while trying to delete previous file (%s), pls contact a support team!', $e->getMessage()),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return true;
    }
}
