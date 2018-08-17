<?php

namespace Shopsys\ShopBundle\Form\Admin;

use Shopsys\FrameworkBundle\Form\Admin\Product\ProductEditFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

class ProductEditFormTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return ProductEditFormType::class;
    }
}
