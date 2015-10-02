<?php

namespace SS6\ShopBundle\Component\Image\Config\Exception;

use Exception;
use SS6\ShopBundle\Component\Image\Config\Exception\ImageConfigException;

class ImageTypeNotFoundException extends Exception implements ImageConfigException {

	/**
	 * @var string
	 */
	private $entityClass;

	/**
	 * @var string
	 */
	private $imageType;

	/**
	 * @param string $entityClass
	 * @param string $imageType
	 * @param \Exception $previous
	 */
	public function __construct($entityClass, $imageType, Exception $previous = null) {
		$this->entityClass = $entityClass;
		$this->imageType = $imageType;

		parent::__construct('Image type "' . $imageType . '" not found for entity "' . $entityClass . '".', 0, $previous);
	}

	/**
	 * @return string
	 */
	public function getEntityClass() {
		return $this->entityClass;
	}

	/**
	 * @return string
	 */
	public function getImageType() {
		return $this->imageType;
	}

}