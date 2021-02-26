<?php

namespace App\ArrayGenerator\Catalog;

use App\Document\Catalog\Catalog;
use App\Document\Catalog\Picture;

class CatalogArrayGenerator extends BaseCatalogToArray
{
    /**
     * @param Catalog $object
     * @param bool    $fullInfo
     * @return array
     */
    public function toArray($object, $fullInfo = true): array
    {
        return [
            'id'            => $object->getId(),
            'name'          => $object->getName(),
            'description'   => $object->getDescription(),
            'createdAt'     => $object->getCreatedAt(),
            'updatedAt'     => $object->getUpdatedAt(),
            'parent'        => $object->getParent() ? [
                'id' => $object->getParent()->getId(),
                'name' => $object->getParent()->getName()
            ] : null,
            'childrens'     => array_map(function (Catalog $catalog) {
                return $this->getCatalogSmall($catalog);
            }, $object->getChildrens()->toArray()),
            'detail' => $this->router->generate('CATALOG_DETAIL', [
                'id' => $object->getId(),
            ]),
            'breadcrumbs'   => $this->getFormatedBreadcrumbs($object, $fullInfo),
        ];
    }

    /**
     * @param Catalog $catalog
     * @return array
     */
    private function getCatalogSmall(Catalog $catalog)
    {
        return [
            'id'             => $catalog->getId(),
            'name'           => $catalog->getName(),
            'primaryPicture' => $catalog->getPrimaryPicture(),
//            'description'   => $catalog->getDescription(),
//            'createdAt'     => $catalog->getCreatedAt(),
//            'updatedAt'     => $catalog->getUpdatedAt(),
            'detail' => $this->router->generate('CATALOG_DETAIL', [
                'id' => $catalog->getId(),
            ]),
        ];
    }

    /**
     * @param Catalog $catalog
     * @param bool    $fullInfo
     * @return array[]|null
     */
    private function getFormatedBreadcrumbs(Catalog $catalog, $fullInfo)
    {
        if (!$fullInfo) {
            return null;
        }
        return $this->catalogHelpers->getBreadCrumbs($catalog)->toArray();
    }


    /**
     * @param Catalog $catalog
     * @return array|null
     */
    private function getPrimaryPicture(Catalog $catalog)
    {
        if (!$primary = $catalog->getPrimaryPicture()) {
            return null;
        }
        return $primary->getResolutions()->toArray();
    }
}