<?php

namespace Shopsys\FrameworkBundle\Form\Admin\Product;

use Ivory\CKEditorBundle\Form\Type\CKEditorType;
use Shopsys\FormTypesBundle\MultidomainType;
use Shopsys\FormTypesBundle\YesNoType;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Form\CategoriesType;
use Shopsys\FrameworkBundle\Form\DatePickerType;
use Shopsys\FrameworkBundle\Form\DisplayOnlyType;
use Shopsys\FrameworkBundle\Form\DisplayOnlyUrlType;
use Shopsys\FrameworkBundle\Form\FormRenderingConfigurationExtension;
use Shopsys\FrameworkBundle\Form\GroupType;
use Shopsys\FrameworkBundle\Form\Locale\LocalizedType;
use Shopsys\FrameworkBundle\Form\LocalizedFullWidthType;
use Shopsys\FrameworkBundle\Form\ProductCalculatedPricesType;
use Shopsys\FrameworkBundle\Form\UrlListType;
use Shopsys\FrameworkBundle\Form\ValidationGroup;
use Shopsys\FrameworkBundle\Form\WarningMessageType;
use Shopsys\FrameworkBundle\Model\Category\CategoryFacade;
use Shopsys\FrameworkBundle\Model\Pricing\Vat\VatFacade;
use Shopsys\FrameworkBundle\Model\Product\Availability\AvailabilityFacade;
use Shopsys\FrameworkBundle\Model\Product\Brand\BrandFacade;
use Shopsys\FrameworkBundle\Model\Product\Flag\FlagFacade;
use Shopsys\FrameworkBundle\Model\Product\Product;
use Shopsys\FrameworkBundle\Model\Product\Unit\UnitFacade;
use Shopsys\FrameworkBundle\Model\Seo\SeoSettingFacade;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class ProductFormType extends AbstractType
{
    const VALIDATION_GROUP_AUTO_PRICE_CALCULATION = 'autoPriceCalculation';
    const VALIDATION_GROUP_USING_STOCK = 'usingStock';
    const VALIDATION_GROUP_USING_STOCK_AND_ALTERNATE_AVAILABILITY = 'usingStockAndAlternateAvailability';
    const VALIDATION_GROUP_NOT_USING_STOCK = 'notUsingStock';

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Vat\VatFacade
     */
    private $vatFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Availability\AvailabilityFacade
     */
    private $availabilityFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Brand\BrandFacade
     */
    private $brandFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Flag\FlagFacade
     */
    private $flagFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Unit\UnitFacade
     */
    private $unitFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    private $domain;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Seo\SeoSettingFacade
     */
    private $seoSettingFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Category\CategoryFacade
     */
    private $categoryFacade;

    public function __construct(
        VatFacade $vatFacade,
        AvailabilityFacade $availabilityFacade,
        BrandFacade $brandFacade,
        FlagFacade $flagFacade,
        UnitFacade $unitFacade,
        Domain $domain,
        SeoSettingFacade $seoSettingFacade,
        CategoryFacade $categoryFacade
    ) {
        $this->vatFacade = $vatFacade;
        $this->availabilityFacade = $availabilityFacade;
        $this->brandFacade = $brandFacade;
        $this->flagFacade = $flagFacade;
        $this->unitFacade = $unitFacade;
        $this->domain = $domain;
        $this->seoSettingFacade = $seoSettingFacade;
        $this->categoryFacade = $categoryFacade;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $product = $options['product'];
        /* @var $product \Shopsys\FrameworkBundle\Model\Product\Product|null */

        $disabledItemInMainVariantAttr = [];
        if ($this->isProductMainVariant($product)) {
            $disabledItemInMainVariantAttr = [
                'disabledField' => true,
                'disabledFieldTitle' => t('This item can be set in product detail of a specific variant'),
                'disabledFieldClass' => 'form-line__disabled',
            ];
        }

        if ($product !== null && $product->isVariant()) {
            $builder->add($this->createVariantGroup($builder, $product));
        }

        $builder->add('name', LocalizedFullWidthType::class, [
            'required' => false,
            'entry_options' => [
                'constraints' => [
                    new Constraints\Length(['max' => 255, 'maxMessage' => 'Product name cannot be longer than {{ limit }} characters']),
                ],
            ],
            'label' => t('Name'),
            'render_form_row' => false,
        ]);

        $builder->add($this->createBasicInformationGroup($builder, $product, $disabledItemInMainVariantAttr));
        $builder->add($this->createDisplayAvailabilityGroup($builder, $product, $disabledItemInMainVariantAttr));
        $builder->add($this->createPricesGroup($builder, $product));
        $builder->add($this->createDescriptionsGroup($builder, $product));
        $builder->add($this->createShortDescriptionsGroup($builder, $product));
        $builder->add($this->createSeoGroup($builder, $product));
    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired('product')
            ->setAllowedTypes('product', [Product::class, 'null'])
            ->setDefaults([
                'attr' => ['novalidate' => 'novalidate'],
                'validation_groups' => function (FormInterface $form) {
                    $validationGroups = [ValidationGroup::VALIDATION_GROUP_DEFAULT];
                    $productData = $form->getData();
                    /* @var $productData \Shopsys\FrameworkBundle\Model\Product\ProductData */

                    if ($productData->usingStock) {
                        $validationGroups[] = self::VALIDATION_GROUP_USING_STOCK;
                        if ($productData->outOfStockAction === Product::OUT_OF_STOCK_ACTION_SET_ALTERNATE_AVAILABILITY) {
                            $validationGroups[] = self::VALIDATION_GROUP_USING_STOCK_AND_ALTERNATE_AVAILABILITY;
                        }
                    } else {
                        $validationGroups[] = self::VALIDATION_GROUP_NOT_USING_STOCK;
                    }

                    if ($productData->priceCalculationType === Product::PRICE_CALCULATION_TYPE_AUTO) {
                        $validationGroups[] = self::VALIDATION_GROUP_AUTO_PRICE_CALCULATION;
                    }

                    return $validationGroups;
                },
            ]);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @param array $disabledItemInMainVariantAttr
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createBasicInformationGroup(FormBuilderInterface $builder, ?Product $product, $disabledItemInMainVariantAttr = [])
    {
        $builderBasicInformationGroup = $builder->create('basicInformationGroup', GroupType::class, [
            'label' => t('Basic information'),
        ]);

        $builderBasicInformationGroup->add('catnum', TextType::class, [
            'required' => false,
            'constraints' => [
                new Constraints\Length(['max' => 100, 'maxMessage' => 'Catalogue number cannot be longer then {{ limit }} characters']),
            ],
            'disabled' => $this->isProductMainVariant($product),
            'attr' => $disabledItemInMainVariantAttr,
            'label' => t('Catalogue number'),
        ])
            ->add('partno', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Constraints\Length(['max' => 100, 'maxMessage' => 'Part number cannot be longer than {{ limit }} characters']),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'attr' => $disabledItemInMainVariantAttr,
                'label' => t('PartNo (serial number)'),
            ])
            ->add('ean', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Constraints\Length(['max' => 100, 'maxMessage' => 'EAN cannot be longer then {{ limit }} characters']),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'attr' => $disabledItemInMainVariantAttr,
                'label' => t('EAN'),
            ]);

        if ($product !== null) {
            $builderBasicInformationGroup->add('id', DisplayOnlyType::class, [
                'label' => t('ID'),
                'data' => $product->getId(),
            ]);
        }

        $builderBasicInformationGroup
            ->add('flags', ChoiceType::class, [
                'required' => false,
                'choices' => $this->flagFacade->getAll(),
                'choice_label' => 'name',
                'choice_value' => 'id',
                'multiple' => true,
                'expanded' => true,
                'label' => t('Flags'),
            ])
            ->add('brand', ChoiceType::class, [
                'required' => false,
                'choices' => $this->brandFacade->getAll(),
                'choice_label' => 'name',
                'choice_value' => 'id',
                'placeholder' => t('-- Choose brand --'),
                'label' => t('Brand'),
            ]);

        return $builderBasicInformationGroup;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createShortDescriptionsGroup(FormBuilderInterface $builder, ?Product $product)
    {
        $builderShortDescriptionGroup = $builder->create('shortDescriptionsGroup', GroupType::class, [
            'label' => t('Short description'),
        ]);

        if ($this->isProductVariant($product)) {
            $builderShortDescriptionGroup->add('shortDescriptions', DisplayOnlyType::class, [
                'mapped' => false,
                'required' => false,
                'data' => t('Short description can be set in the main variant.'),
                'attr' => [
                    'class' => 'form-input-disabled form-line--disabled position__actual font-size-13',
                ],
            ]);
        } else {
            $builderShortDescriptionGroup
                ->add('shortDescriptions', MultidomainType::class, [
                    'entry_type' => TextareaType::class,
                    'required' => false,
                    'disabled' => $this->isProductVariant($product),
                    'display_format' => FormRenderingConfigurationExtension::DISPLAY_FORMAT_MULTIDOMAIN_ROWS_NO_PADDING,
                ]);
        }

        return $builderShortDescriptionGroup;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createDescriptionsGroup(FormBuilderInterface $builder, ?Product $product)
    {
        $builderDescriptionGroup = $builder->create('descriptionsGroup', GroupType::class, [
            'label' => t('Description'),
        ]);

        if ($this->isProductVariant($product)) {
            $builderDescriptionGroup->add('descriptions', DisplayOnlyType::class, [
                'mapped' => false,
                'required' => false,
                'data' => t('Description can be set on product detail of the main product.'),
                'attr' => [
                    'class' => 'form-input-disabled form-line--disabled position__actual font-size-13',
                ],
            ]);
        } else {
            $builderDescriptionGroup
                ->add('descriptions', MultidomainType::class, [
                    'entry_type' => CKEditorType::class,
                    'required' => false,
                    'disabled' => $this->isProductVariant($product),
                    'display_format' => FormRenderingConfigurationExtension::DISPLAY_FORMAT_MULTIDOMAIN_ROWS_NO_PADDING,
                ]);
        }

        return $builderDescriptionGroup;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @param array $disabledItemInMainVariantAttr
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createDisplayAvailabilityGroup(FormBuilderInterface $builder, ?Product $product, $disabledItemInMainVariantAttr = [])
    {
        $productMainCategoriesIndexedByDomainId = [];
        if ($product !== null) {
            $productMainCategoriesIndexedByDomainId = $this->categoryFacade->getProductMainCategoriesIndexedByDomainId($product);
        }

        $mainCategoriesOptionsByDomainId = [];
        foreach ($this->domain->getAll() as $domainConfig) {
            $domainId = $domainConfig->getId();

            if (count($productMainCategoriesIndexedByDomainId) > 0) {
                $productMainCategory = $productMainCategoriesIndexedByDomainId[$domainId];
                $mainCategoriesOptionsByDomainId[$domainId] = [
                    'attr' => [
                        'readonly' => 'readonly',
                        'show_label' => true,
                    ],
                    'label' => count($productMainCategoriesIndexedByDomainId) > 1 ? $this->domain->getDomainConfigById($domainId)->getName() : t('Main category'),
                    'data' => $productMainCategory === null ? '-' : $productMainCategory->getName(),
                ];
            }
        }

        $categoriesOptionsByDomainId = [];
        foreach ($this->domain->getAllIds() as $domainId) {
            $categoriesOptionsByDomainId[$domainId] = [
                'domain_id' => $domainId,
            ];
        }

        $builderDisplayAvailabilityGroup = $builder->create('displayAvailabilityGroup', GroupType::class, [
            'label' => t('Display and availability'),
        ]);

        $builderDisplayAvailabilityGroup
            ->add('hidden', YesNoType::class, [
                'required' => false,
                'label' => t('Hide product'),
            ]);

        if ($product !== null && $product->isUsingStock() && $product->getCalculatedHidden() && $product->getStockQuantity() <= 0) {
            $builderDisplayAvailabilityGroup
                ->add('productCalculatedHiddenInfo', WarningMessageType::class, [
                    'data' => t('Product is hidden due to sellout'),
                ]);
        }

        $builderDisplayAvailabilityGroup
            ->add('sellingFrom', DatePickerType::class, [
                'required' => false,
                'constraints' => [
                    new Constraints\Date(['message' => 'Enter date in DD.MM.YYYY format']),
                ],
                'invalid_message' => 'Enter date in DD.MM.YYYY format',
                'label' => t('Selling start date'),
            ])
            ->add('sellingTo', DatePickerType::class, [
                'required' => false,
                'constraints' => [
                    new Constraints\Date(['message' => 'Enter date in DD.MM.YYYY format']),
                ],
                'invalid_message' => 'Enter date in DD.MM.YYYY format',
                'label' => t('Selling end date'),
            ])
            ->add('sellingDenied', YesNoType::class, [
                'required' => false,
                'label' => t('Exclude from sale'),
                'attr' => [
                    'icon' => true,
                    'iconTitle' => t('Products excluded from sale can\'t be displayed on lists and can\'t be searched. Product detail is available by direct access from the URL, but it is not possible to add product to cart.'),
                ],
            ]);

        if ($product !== null && $product->isUsingStock() && $product->getCalculatedSellingDenied()
            && $product->getStockQuantity() <= 0
        ) {
            $builderDisplayAvailabilityGroup
                ->add('productCalculatedSellingDeniedInfo', WarningMessageType::class, [
                    'data' => t('Product is excluded from the sale due to sellout'),
                ]);
        }

        if ($product !== null) {
            $builderDisplayAvailabilityGroup
                ->add('productMainCategories', MultidomainType::class, [
                    'entry_type' => TextType::class,
                    'required' => false,
                    'mapped' => false,
                    'options_by_domain_id' => $mainCategoriesOptionsByDomainId,
                    'label' => t('Main category on domains'),
                ]);
        }

        if ($this->isProductVariant($product)) {
            $builderDisplayAvailabilityGroup
                ->add('categoriesByDomainId', DisplayOnlyType::class, [
                    'data' => t('You can set the categories on product detail of the main variant'),
                    'label' => t('Assign to category'),
                ]);
        } else {
            $builderDisplayAvailabilityGroup
                ->add('categoriesByDomainId', MultidomainType::class, [
                    'required' => false,
                    'entry_type' => CategoriesType::class,
                    'options_by_domain_id' => $categoriesOptionsByDomainId,
                    'disabled' => $this->isProductVariant($product),
                    'label' => t('Assign to category'),
                    'display_format' => FormRenderingConfigurationExtension::DISPLAY_FORMAT_MULTIDOMAIN_ROWS_NO_PADDING,
                ]);
        }
        $builderDisplayAvailabilityGroup
            ->add('unit', ChoiceType::class, [
                'required' => true,
                'choices' => $this->unitFacade->getAll(),
                'choice_label' => 'name',
                'choice_value' => 'id',
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please choose unit',
                    ]),
                ],
                'label' => t('Unit'),
            ])
            ->add('usingStock', YesNoType::class, [
                'required' => false,
                'disabled' => $this->isProductMainVariant($product),
                'attr' => $disabledItemInMainVariantAttr,
                'label' => t('Use stocks'),
            ]);

        $builderStockGroup = $builder->create('stockGroup', FormType::class, [
            'render_form_row' => false,
            'inherit_data' => true,
            'is_group_container_to_render_as_the_last_one' => false,
            'attr' => [
                'class' => 'js-product-using-stock form-line__js',
            ],
        ]);

        $builderDisplayAvailabilityGroup->add($builderStockGroup);

        $builderStockGroup
            ->add('stockQuantity', IntegerType::class, [
                'required' => true,
                'invalid_message' => 'Please enter a number',
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please enter stock quantity',
                        'groups' => self::VALIDATION_GROUP_USING_STOCK,
                    ]),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'attr' => $disabledItemInMainVariantAttr,
                'label' => t('Available in stock'),
            ])
            ->add('outOfStockAction', ChoiceType::class, [
                'required' => true,
                'expanded' => false,
                'choices' => [
                    t('Set alternative availability') => Product::OUT_OF_STOCK_ACTION_SET_ALTERNATE_AVAILABILITY,
                    t('Hide product') => Product::OUT_OF_STOCK_ACTION_HIDE,
                    t('Exclude from sale') => Product::OUT_OF_STOCK_ACTION_EXCLUDE_FROM_SALE,
                ],
                'placeholder' => t('-- Choose action --'),
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please choose action',
                        'groups' => self::VALIDATION_GROUP_USING_STOCK,
                    ]),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'attr' => $disabledItemInMainVariantAttr,
                'label' => t('Action after sellout'),
            ]);

        $builderStockGroup
            ->add('outOfStockAvailability', ChoiceType::class, [
                'required' => true,
                'choices' => $this->availabilityFacade->getAll(),
                'choice_label' => 'name',
                'choice_value' => 'id',
                'placeholder' => t('-- Choose availability --'),
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please choose availability',
                        'groups' => self::VALIDATION_GROUP_USING_STOCK_AND_ALTERNATE_AVAILABILITY,
                    ]),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'attr' => array_merge($disabledItemInMainVariantAttr, [
                    'class' => 'js-product-using-stock-and-alternate-availability',
                ]),
                'label' => t('Availability after sellout'),
            ]);

        $builderDisplayAvailabilityGroup
            ->add('availability', ChoiceType::class, [
                'required' => true,
                'choices' => $this->availabilityFacade->getAll(),
                'choice_label' => 'name',
                'choice_value' => 'id',
                'placeholder' => t('-- Choose availability --'),
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please choose availability',
                        'groups' => self::VALIDATION_GROUP_NOT_USING_STOCK,
                    ]),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'attr' => array_merge($disabledItemInMainVariantAttr, [
                    'class' => 'js-product-not-using-stock',
                ]),
                'label' => t('Availability'),
            ]);

        $builderDisplayAvailabilityGroup
            ->add('orderingPriority', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(['message' => 'Please enter sorting priority']),
                ],
                'label' => t('Sorting priority'),
            ]);

        return $builderDisplayAvailabilityGroup;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createPricesGroup(FormBuilderInterface $builder, ?Product $product)
    {
        $builderPricesGroup = $builder->create('pricesGroup', GroupType::class, [
            'label' => t('Prices'),
        ]);

        $builderPricesGroup
            ->add('priceCalculationType', ChoiceType::class, [
                'required' => true,
                'expanded' => true,
                'choices' => [
                    t('Automatically') => Product::PRICE_CALCULATION_TYPE_AUTO,
                    t('Manually') => Product::PRICE_CALCULATION_TYPE_MANUAL,
                ],
                'disabled' => $this->isProductMainVariant($product),
                'label' => t('Sale prices calculation'),
            ])
            ->add('vat', ChoiceType::class, [
                'required' => true,
                'choices' => $this->vatFacade->getAllIncludingMarkedForDeletion(),
                'choice_label' => 'name',
                'choice_value' => 'id',
                'constraints' => [
                    new Constraints\NotBlank(['message' => 'Please enter VAT rate']),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'label' => t('VAT'),
            ]);

        $productCalculatedPricesGroup = $builder->create('productCalculatedPricesGroup', ProductCalculatedPricesType::class, [
            'product' => $product,
            'inherit_data' => true,
            'render_form_row' => false,
        ]);

        $builderPricesGroup->add($productCalculatedPricesGroup);
        $productCalculatedPricesGroup
            ->add('price', MoneyType::class, [
                'currency' => false,
                'scale' => 6,
                'required' => true,
                'invalid_message' => 'Please enter price in correct format (positive number with decimal separator)',
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please enter price',
                        'groups' => self::VALIDATION_GROUP_AUTO_PRICE_CALCULATION,
                    ]),
                    new Constraints\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Price must be greater or equal to {{ compared_value }}',
                        'groups' => self::VALIDATION_GROUP_AUTO_PRICE_CALCULATION,
                    ]),
                ],
                'disabled' => $this->isProductMainVariant($product),
                'label' => t('Base price'),
            ]);

        return $builderPricesGroup;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createSeoGroup(FormBuilderInterface $builder, ?Product $product)
    {
        $seoTitlesOptionsByDomainId = [];
        $seoMetaDescriptionsOptionsByDomainId = [];
        $seoH1OptionsByDomainId = [];
        foreach ($this->domain->getAll() as $domainConfig) {
            $domainId = $domainConfig->getId();
            $locale = $domainConfig->getLocale();

            $seoTitlesOptionsByDomainId[$domainId] = [
                'attr' => [
                    'placeholder' => $this->getTitlePlaceholder($locale, $product),
                    'class' => 'js-dynamic-placeholder',
                    'data-placeholder-source-input-id' => 'product_edit_form_productData_name_' . $locale,
                ],
            ];
            $seoMetaDescriptionsOptionsByDomainId[$domainId] = [
                'attr' => [
                    'placeholder' => $this->seoSettingFacade->getDescriptionMainPage($domainId),
                ],
            ];
            $seoH1OptionsByDomainId[$domainId] = $seoTitlesOptionsByDomainId[$domainId];
        }
        $builderSeoGroup = $builder->create('seoGroup', GroupType::class, [
            'label' => t('Seo'),
        ]);

        $builderSeoGroup
            ->add('seoTitles', MultidomainType::class, [
                'entry_type' => TextType::class,
                'required' => false,
                'options_by_domain_id' => $seoTitlesOptionsByDomainId,
                'macro' => [
                    'name' => 'seoFormRowMacros.multidomainRow',
                    'recommended_length' => 60,
                ],
                'label' => t('Page title'),
            ])
            ->add('seoMetaDescriptions', MultidomainType::class, [
                'entry_type' => TextareaType::class,
                'required' => false,
                'options_by_domain_id' => $seoMetaDescriptionsOptionsByDomainId,
                'macro' => [
                    'name' => 'seoFormRowMacros.multidomainRow',
                    'recommended_length' => 155,
                ],
                'label' => t('Meta description'),
            ])
            ->add('seoH1s', MultidomainType::class, [
                'entry_type' => TextType::class,
                'required' => false,
                'options_by_domain_id' => $seoH1OptionsByDomainId,
                'label' => t('Heading (H1)'),
            ]);

        if ($product) {
            $builderSeoGroup->add('urls', UrlListType::class, [
                'route_name' => 'front_product_detail',
                'entity_id' => $product->getId(),
                'label' => t('URL settings'),
            ]);
        }

        return $builderSeoGroup;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param null|Product $product
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    private function createVariantGroup(FormBuilderInterface $builder, ?Product $product)
    {
        $variantGroup = $builder->create('variantGroup', FormType::class, [
            'mapped' => false,
            'attr' => [
                'class' => 'wrap-border',
            ],
            'render_form_row' => false,
        ]);

        $builder->add($variantGroup);
        $variantGroup->add('mainVariantUrl', DisplayOnlyUrlType::class, [
            'label' => t('Product is variant'),
            'data' => [
                'route_name' => 'admin_product_edit',
                'id' => $product->getMainVariant()->getId(),
                'name' => $product->getMainVariant()->getName(),
            ],
            'attr' => [
                'class' => 'wrap-border',
            ],
        ]);

        $variantGroup->add('variantAlias', LocalizedType::class, [
            'required' => false,
            'entry_options' => [
                'constraints' => [
                    new Constraints\Length(['max' => 255, 'maxMessage' => 'Variant alias cannot be longer then {{ limit }} characters']),
                ],
            ],
            'label' => t('Variant alias'),
            'render_form_row' => true,
        ]);

        return $variantGroup;
    }

    /**
     * @param string $locale
     * @param \Shopsys\FrameworkBundle\Model\Product\Product|null $product
     * @return string
     */
    private function getTitlePlaceholder($locale, Product $product = null)
    {
        return $product !== null ? $product->getName($locale) : '';
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product|null $product
     * @return bool
     */
    private function isProductMainVariant(?Product $product)
    {
        return $product !== null && $product->isMainVariant();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product|null $product
     * @return bool
     */
    private function isProductVariant(?Product $product)
    {
        return $product !== null && $product->isVariant();
    }
}
