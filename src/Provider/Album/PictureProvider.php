<?php

namespace App\Provider\Album;

use App\Document\Album\Picture;
use App\Model\ApiResponse\ApiResponse;
use App\Repository\Album\PictureRepository;
use App\Utils\Album\PictureArrayGenerator;
use App\Utils\Response\ErrorCodes;
use Doctrine\ODM\MongoDB\MongoDBException;

class PictureProvider
{
    /**
     * @var PictureRepository
     */
    private $pictureRepository;

    /**
     * @var PictureArrayGenerator
     */
    private $pictureArrayGenerator;

    /**
     * PictureProvider constructor.
     * @param PictureRepository     $pictureRepository
     * @param PictureArrayGenerator $pictureArrayGenerator
     */
    public function __construct(
        PictureRepository $pictureRepository,
        PictureArrayGenerator $pictureArrayGenerator
    )
    {
        $this->pictureRepository     = $pictureRepository;
        $this->pictureArrayGenerator = $pictureArrayGenerator;
    }

    /**
     * @param string $id
     * @return ApiResponse
     */
    public function getPictureById(string $id)
    {
        if (!$picture = $this->pictureRepository->getPictureById($id)) {
            return (new ApiResponse(null, ErrorCodes::NO_PICTURE));
        }

        return (new ApiResponse($this->pictureArrayGenerator->pictureToArray($picture)));
    }

    /**
     * @return ApiResponse
     * @throws MongoDBException
     */
    public function getPictures()
    {
        $pictures = array_map(function (Picture $picture) {
            return $this->pictureArrayGenerator->pictureToArray($picture);
        }, $this->pictureRepository->getAllPictures()->toArray());

        return (new ApiResponse($pictures));
    }
}