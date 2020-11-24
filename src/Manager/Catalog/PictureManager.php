<?php

namespace App\Manager\Catalog;

use App\Document\Catalog\Exif;
use App\Document\Catalog\License;
use App\Document\Catalog\Picture;
use App\Document\Catalog\Position;
use App\Document\Catalog\Resolution;
use App\Model\ApiResponse\ApiResponse;
use App\Manager\BaseManager;
use App\Repository\Catalog\PictureRepository;
use App\Utils\Catalog\LicenseHelper;
use App\Utils\Catalog\PictureArrayGenerator;
use App\Utils\Catalog\PictureFileManager;
use App\Utils\Catalog\PictureHelpers;
use App\Utils\Response\ErrorCodes;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use PHPExif\Reader\Reader;
use Symfony\Component\HttpFoundation\RequestStack;

class PictureManager extends BaseManager
{
    const BODY_PARAM_NAME              = 'name';
    const BODY_PARAM_ORIGINALFILENAME  = 'originalFilename';
    const BODY_PARAM_SOURCE            = 'source';
    const BODY_PARAM_DESCRIPTION       = 'description';
    const BODY_PARAM_TAKEN_AT          = 'takenAt';
    const BODY_PARAM_ID_CATALOG        = 'idCatalog';
    const BODY_PARAM_FILE              = 'file';
    const BODY_PARAM_LICENSE_NAME      = 'name';
    const BODY_PARAM_LICENSE_IS_EDTIED = 'isEdited';
    const BODY_PARAM_LICENSE           = 'license';


    /**
     * @var PictureArrayGenerator
     */
    private $pictureArrayGenerator;

    /**
     * @var PictureHelpers
     */
    private $pictureHelpers;

    /**
     * @var PictureRepository
     */
    private $pictureRepository;

    /**
     * @var PictureFileManager
     */
    private $pictureFileManager;

    /**
     * PictureManager constructor.
     * @param DocumentManager       $dm
     * @param RequestStack          $requestStack
     * @param PictureArrayGenerator $pictureArrayGenerator
     * @param PictureHelpers        $pictureHelpers
     * @param PictureRepository     $pictureRepository
     * @param PictureFileManager    $pictureFileManager
     */
    public function __construct(
        DocumentManager $dm,
        RequestStack $requestStack,
        PictureArrayGenerator $pictureArrayGenerator,
        PictureHelpers $pictureHelpers,
        PictureRepository $pictureRepository,
        PictureFileManager $pictureFileManager
    )
    {
        parent::__construct($dm, $requestStack);
        $this->pictureArrayGenerator = $pictureArrayGenerator;
        $this->pictureHelpers        = $pictureHelpers;
        $this->pictureRepository     = $pictureRepository;
        $this->pictureFileManager    = $pictureFileManager;
    }

    public function setFields()
    {
        $this->name             = $this->body[self::BODY_PARAM_NAME] ?? null;
        $this->source           = $this->body[self::BODY_PARAM_SOURCE] ?? null;
        $this->description      = $this->body[self::BODY_PARAM_DESCRIPTION] ?? null;
        $this->originalFilename = $this->body[self::BODY_PARAM_ORIGINALFILENAME] ?? null;
        $this->takenAt          = $this->body[self::BODY_PARAM_TAKEN_AT] ?? null;
        $this->file             = $this->body[self::BODY_PARAM_FILE] ?? null;

        $this->licenseName     = null;
        $this->licenseIsEdited = null;

        if ($license = $this->body[self::BODY_PARAM_LICENSE] ?? null) {
            $this->licenseName     = $license[self::BODY_PARAM_LICENSE_NAME] ?? null;
            $this->licenseIsEdited = $license[self::BODY_PARAM_LICENSE_IS_EDTIED] ?? false;
        }
    }

    /**
     * @return ApiResponse
     * @throws MongoDBException
     */
    public function create()
    {
        $this->checkMissedField();
        if ($this->apiResponse->isError()) {
            return $this->apiResponse;
        }

        if (!LicenseHelper::isValidLicense($this->licenseName)) {
            $this->apiResponse->addError(ErrorCodes::LICENSE_NOT_VALID);
            return $this->apiResponse;
        }

        $file             = $this->pictureHelpers->base64toImage($this->file, $this->originalFilename);
        $originalFilename = sprintf('%s.%s', uniqid('picture'), $file->getClientOriginalExtension());

        // reader with Native adapter
        $reader = Reader::factory(Reader::TYPE_NATIVE);
// reader with Exiftool adapter
//$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_EXIFTOOL);
        $exifData = $reader->read($file->getRealPath());

        $picture = new Picture();

        $this->setExif($exifData, $picture);
        $this->setPosition($exifData, $picture);
        $this->setResolution($exifData, $picture, $file);
        $this->setLicense($picture);
        $picture->setTakenAt(new \DateTime($this->takenAt));

        $picture->setName($this->name);
        $picture->setSource($this->source);
        $picture->setDescription($this->description);
        $picture->setOriginalFileName($originalFilename);
        $picture->setHash(PictureHelpers::getHash($file));
        $picture->setTypeMime($file->getMimeType());

        $this->pictureFileManager->upload($file, $picture);

        $this->dm->persist($picture);
        $this->dm->flush();

        $this->apiResponse->setData($this->pictureArrayGenerator->toArray($picture));
        return $this->apiResponse;
    }


    public function edit(string $id)
    {
        if (!$picture = $this->pictureRepository->getPictureById($id)) {
            $this->apiResponse->addError(ErrorCodes::NO_PICTURE);
            return $this->apiResponse;
        }

        $picture->setName($this->name ?: $picture->getName());
        $picture->setSource($this->source ?: $picture->getSource());
        $picture->setDescription($this->description ?: $picture->getDescription());
        $picture->setTakenAt(new \DateTime($this->takenAt) ?: $picture->getTakenAt());

        if (!$picture->getLicense()) {
            $picture->setLicense(new License());
        }

        if (!LicenseHelper::isValidLicense($this->licenseName)) {
            $this->apiResponse->addError(ErrorCodes::LICENSE_NOT_VALID);
            return $this->apiResponse;
        }

        $this->setLicense($picture);

        if (!$this->file) {
            $this->apiResponse->setData($this->pictureArrayGenerator->toArray($picture));
            $this->dm->flush();
            return $this->apiResponse;
        }

        $file             = $this->pictureHelpers->base64toImage($this->file, $this->originalFilename);
        $originalFilename = sprintf('%s.%s', uniqid('picture'), $file->getClientOriginalExtension());

        $hash = PictureHelpers::getHash($file);

        if ($hash === $picture->getHash()) {
            $this->apiResponse->setData($this->pictureArrayGenerator->toArray($picture));
            $this->dm->flush();
            return $this->apiResponse;
        }

        // reader with Native adapter
        $reader = Reader::factory(Reader::TYPE_NATIVE);
// reader with Exiftool adapter
//$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_EXIFTOOL);
        $exifData = $reader->read($file->getRealPath());

        $this->pictureFileManager->remove($picture);

        $this->setExif($exifData, $picture);
        $this->setPosition($exifData, $picture);
        $this->setResolution($exifData, $picture, $file);

        $picture->setOriginalFileName($originalFilename);
        $picture->setHash($hash);
        $picture->setTypeMime($file->getMimeType());

        $this->pictureFileManager->upload($file, $picture);

        $this->dm->flush();
        $this->apiResponse->setData($this->pictureArrayGenerator->toArray($picture));
        return $this->apiResponse;
    }

    /**
     * @param string $id
     * @return ApiResponse
     * @throws MongoDBException
     */
    public function delete(string $id)
    {
        if (!$picture = $this->pictureRepository->getPictureById($id)) {
            return (new ApiResponse(null, ErrorCodes::NO_IMAGE));
        }
        $this->pictureFileManager->remove($picture);
        $this->dm->remove($picture);
        $this->dm->flush();

        return (new ApiResponse([]));
    }

    /**
     * @param         $exifData
     * @param Picture $picture
     */
    private function setExif($exifData, Picture $picture)
    {
        if (!$exifData) {
            return;
        }

        $exif = new Exif();

        $exif->setModel($exifData->getCamera() ?: null);
//        $exif->setMake();
        $exif->setAperture($exifData->getAperture() ?: null);
        $exif->setIso($exifData->getIso() ?: null);
        $exif->setExposure($exifData->getExposure() ?: null);
        $exif->setFocalLength($exifData->getFocalLength() ?: null);
//        $exif->setFlash();
        $picture->setExif($exif);
    }

    /**
     * @param         $exifData
     * @param Picture $picture
     */
    private function setPosition($exifData, Picture $picture)
    {
        if (!$exifData) {
            return;
        }
        return;
        $position = new Position(10.5464, 10.657867);
        $picture->setPosition($position);
    }

    /**
     * @param         $exifData
     * @param Picture $picture
     * @param         $file
     */
    private function setResolution($exifData, Picture $picture, $file)
    {
        $resolution = new Resolution();

        $resolution->setSize($file->getSize());
        $resolution->setSizeLabel('original');

        if ($exifData) {
            $resolution->setWidth($exifData->getWidth() ?: null);
            $resolution->setHeight($exifData->getHeight() ?: null);
        }

        $picture->addResolution($resolution);
    }

    /**
     * @param Picture $picture
     */
    private function setLicense(Picture $picture)
    {
        $licenses = new License();

        $licenses->setName($this->licenseName ?: $picture->getLicense()->getName());
        $licenses->setIsEdited($this->licenseIsEdited ?: $picture->getLicense()->isEdited());

        $picture->setLicense($licenses);
    }

    /**
     * @return string[]
     */
    public function requiredField()
    {
        return [
            self::BODY_PARAM_NAME,
            self::BODY_PARAM_SOURCE,
            self::BODY_PARAM_FILE,
            self::BODY_PARAM_ORIGINALFILENAME,
        ];
    }
}