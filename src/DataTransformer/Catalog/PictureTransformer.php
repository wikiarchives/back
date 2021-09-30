<?php

namespace App\DataTransformer\Catalog;

use App\Document\Catalog\Picture;
use App\Service\Catalog\PictureFileManager;
use App\Utils\Catalog\CatalogHelpers;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class PictureTransformer extends BaseCatalogTransformer
{
    /**
     * @var PictureFileManager
     */
    private PictureFileManager $pictureFileManager;

    public function __construct(
        RouterInterface $router,
        CatalogHelpers $catalogHelpers,
        PictureFileManager $pictureFileManager
    )
    {
        parent::__construct($router, $catalogHelpers);
        $this->pictureFileManager = $pictureFileManager;
    }

    /**
     * @param Picture $object
     * @return mixed
     */
    public function toArray($object, bool $fullInfo = true)
    {
        return [
            'id'               => $object->getId(),
            'slug'             => $object->getSlug(),
            'originalFilename' => $object->getOriginalFilename(),
            'createdAt'        => $object->getCreatedAt(),
            'updatedAt'        => $object->getUpdatedAt(),
            'catalog'          => $fullInfo && $object->getCatalog() ? [
                'id'   => $object->getCatalog()->getId(),
                'name' => $object->getCatalog()->getName(),
            ] : null,
            'validatedVersion' => $this->versionToArray($object->getValidatedVersion()),
            'versions'         => array_map(function (Picture\Version $version) {
                return $this->versionToArray($version);
            }, $object->getVersions()->toArray()),
            'file'             => $object->getFile() ? [
                'path'             => $object->getFile()->getPath(),
                'webPath'          => $this->pictureFileManager->getWebPath($object),
                'mimeType'         => $object->getFile()->getMimeType(),
                'hash'             => $object->getFile()->getHash(),
                'originalFileName' => $object->getFile()->getOriginalFileName(),
                'size'             => $object->getFile()->getSize(),
            ] : null,
            'detail'           => $this->router->generate('PICTURE_DETAIL', [
                'id' => $object->getId(),
            ]),
            'breadcrumbs'      => $fullInfo ? $this->getBreadcrumb($object) : null,
        ];
    }

    private function versionToArray(?Picture\Version $version)
    {
        if (!$version) {
            return;
        }
        return [
            'id'          => $version->getId(),
            'name'        => $version->getName(),
            'description' => $version->getDescription(),
            'source'      => $version->getSource(),
            'takenAt'     => $version->getTakenAt(),
            'createdAt'   => $version->getCreatedAt(),
        ];
    }

    /**
     * @param $array
     * @return Picture
     * @throws ExceptionInterface
     */
    public function toObject($array): Picture
    {
        return $this->denormalize($array, Picture::class);
    }

    /**
     * @param Picture $picture
     * @return array|null
     */
    private function getBreadcrumb(Picture $picture)
    {
        if (!$catalog = $picture->getCatalog()) {
            return null;
        }
        return $this->catalogHelpers->getBreadCrumbs($catalog)->toArray();
    }
}