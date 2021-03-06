<?php

declare(strict_types=1);

namespace VendorName\ModuleName\Helper;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as CurrencyData;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Quote\Model\Quote\Item\OptionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\AppointedAttributes\Helper\Validation;
use Vendor\Marketplace\Model\ProductFactory as mpProductFactory;
use Vendor\MpAssignProduct\Helper\Data as MpAssignProductData;
use Vendor\MpAssignProduct\Model\AssociatesFactory;
use Vendor\MpAssignProduct\Model\DataFactory;
use  Vendor\MpAssignProduct\Model\ItemsFactory;

/**
 * Class Data
 * @package Vendor\ModuleName\Helper
 */
class Data extends MpAssignProductData
{
    /**
     * @var Validation $validationHelper
     */
    protected $validationHelper;

    /**
     * @var array $skipAttributes
     */
    protected $skipAttributes = ['price', 'quantity_and_stock_status'];

    /**
     * @var DateTimeFactory $dateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ManagerInterface $messageManager
     * @param Session $customerSession
     * @param CustomerFactory $customer
     * @param Filesystem $filesystem
     * @param FormKey $formKey
     * @param CurrencyData $currency
     * @param ResourceConnection $resource
     * @param UploaderFactory $fileUploaderFactory
     * @param ProductFactory $productFactory
     * @param Cart $cart
     * @param mpProductFactory $mpProductFactory
     * @param ItemsFactory $itemsFactory
     * @param DataFactory $dataFactory
     * @param AssociatesFactory $associatesFactory
     * @param OptionFactory $quoteOption
     * @param CollectionFactory $mpProductCollectionFactory
     * @param SellerCollection $sellerCollectionFactory
     * @param ItemsCollection $itemsCollectionFactory
     * @param QuoteCollection $quoteCollectionFactory
     * @param DataCollection $dataCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Registry $coreRegistry
     * @param StockRegistryInterface $stockRegistry
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param PriceCurrencyInterface $priceCurrency
     * @param File $fileDriver
     * @param ConfigurableCollection $configurableCollection
     * @param Option $customOptions
     * @param Validation $validation
     * @param DateTimeFactory $dateTimeFactory
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ManagerInterface $messageManager,
        Session $customerSession,
        CustomerFactory $customer,
        Filesystem $filesystem,
        FormKey $formKey,
        CurrencyData $currency,
        ResourceConnection $resource,
        UploaderFactory $fileUploaderFactory,
        ProductFactory $productFactory,
        Cart $cart,
        mpProductFactory $mpProductFactory,
        ItemsFactory $itemsFactory,
        DataFactory $dataFactory,
        AssociatesFactory $associatesFactory,
        OptionFactory $quoteOption,
        CollectionFactory $mpProductCollectionFactory,
        SellerCollection $sellerCollectionFactory,
        ItemsCollection $itemsCollectionFactory,
        QuoteCollection $quoteCollectionFactory,
        DataCollection $dataCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        Registry $coreRegistry,
        StockRegistryInterface $stockRegistry,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        PriceCurrencyInterface $priceCurrency,
        File $fileDriver,
        ConfigurableCollection $configurableCollection,
        Option $customOptions,
        Validation $validation,
        DateTimeFactory $dateTimeFactory

    ) {
        parent::__construct(
            $context,
            $storeManager,
            $messageManager,
            $customerSession,
            $customer,
            $filesystem,
            $formKey,
            $currency,
            $resource,
            $fileUploaderFactory,
            $productFactory,
            $cart,
            $mpProductFactory,
            $itemsFactory,
            $dataFactory,
            $associatesFactory,
            $quoteOption,
            $mpProductCollectionFactory,
            $sellerCollectionFactory,
            $itemsCollectionFactory,
            $quoteCollectionFactory,
            $dataCollectionFactory,
            $productCollectionFactory,
            $coreRegistry,
            $stockRegistry,
            $transportBuilder,
            $inlineTranslation,
            $priceCurrency,
            $fileDriver,
            $configurableCollection,
            $customOptions
        );
        $this->validationHelper = $validation;
        $this->dateTimeFactory  = $dateTimeFactory;
    }

    /**
     * Validate Data
     *
     * @param array $data
     * @param string $type
     * @return array
     */
    public function validateData(array $data, string $type): array
    {
        if ($type === 'configurable') {
            return $this->validateConfigData($data);
        }

        $rules          = $this->validationHelper->getAttributeRules();
        $result         = [];
        $isSuccess      = true;
        $requiredFields = $this->validationHelper->getRequiredFields();
        $compareResult  = array_diff_key($requiredFields, $data);

        if (count($compareResult)) {
            $result['error'] = true;
            reset($compareResult);
            $result['msg'] = ucfirst(key($compareResult)) . ' is required field';
            return $result;
        }

        foreach ($data as $field => $value) {
            if (isset($rules[$field])) {
                foreach ($rules[$field] as $ruleCode => $ruleStatus) {
                    $ruleParts = explode('-', $ruleCode);
                    $rule      = '';
                    foreach ($ruleParts as $rulePart) {
                        $rule .= $rule
                            ? ucfirst($rulePart)
                            : $rulePart;
                    }
                    if (is_callable([$this->validationHelper, $rule])) {
                        $validationStatus          = $this->validationHelper->$rule($value);
                        $result[$field][$ruleCode] = $validationStatus;
                        if (!$validationStatus) {
                            $isSuccess = false;
                        }
                    }
                }
            }
        }

        $productId = $data['product_id'];
        $assignId  = $data['assign_id'] ?? 0;

        $assigned          = $this->getCollection()
            ->addFieldToFilter('product_id', ['eq' => $productId])
            ->addFieldToFilter('entity_id', ['neq' => $assignId])
            ->addFieldToFilter('seller_id', ['eq' => $this->getCustomerId()]);
        $found             = 0;
        $allowedAttributes = $this->getAllowedAttributes($this->getProduct($productId));
        if (count($assigned)) {
            foreach ($assigned as $item) {
                reset($allowedAttributes);
                $attributes = 0;
                foreach ($allowedAttributes as $attribute) {
                    if ($this->getAdditionalAttributeValue($item, $attribute['id']) !== $data[$attribute['code']]) {
                        $attributes++;
                        break;
                    }
                }
                if (!$attributes) {
                    $found = 1;
                    break;
                }
            }
        }

        if ($found) {
            $result['msg'] = 'You Already have same product with same attributes.';
            $isSuccess     = false;
        }

        $result['error'] = !$isSuccess;
        return $result;
    }

    /**
     * Get Product Collection for given productId
     *
     * @param $productId
     * @return mixed
     */
    public function getAssignProductCollection(int $productId)
    {
        $collection = $this->_itemsCollection->create();
        $collection->addFieldToFilter('product_id', $productId);
        return $collection;
    }

    /**
     * Assign Product to Seller
     *
     * @param array $data
     * @param int $flag [optional]
     * @return array
     * @throws Exception
     */
    public function assignProduct(array $data, int $flag = 0): array
    {
        $result      = [
            'assign_id'  => 0,
            'product_id' => 0,
            'error'      => 0,
            'msg'        => '',
            'qty'        => 0,
            'flag'       => 0,
            'status'     => 1,
            'type'       => 0
        ];
        $productId   = (int) $data['product_id'];
        $condition   = (int) $data['product_condition'];
        $qty         = (int) $data['quantity_and_stock_status'];
        $price       = (float) $data['price'];
        $description = $data['description'];
        $image       = $data['image'];
        $ownerId     = $this->getSellerIdByProductId($productId);
        $sellerId    = $this->getCustomerId();
        $product     = $this->getProduct($productId);
        $type        = $product->getTypeId();
        $date        = $this->getDateByFormat(DateTime::DATE_PHP_FORMAT);

        $result['condition'] = $condition;
        //set zero if we have negative quantity
        $qty                 = max($qty, 0);
        $assignProductData   = [
            'product_id'  => $productId,
            'owner_id'    => $ownerId,
            'seller_id'   => $sellerId,
            'qty'         => $qty,
            'price'       => $price,
            'description' => $description,
            'condition'   => $condition,
            'type'        => $type,
            'created_at'  => $date,
            'image'       => $image,
            'status'      => 1,
        ];
        if ($image === '') {
            unset($assignProductData['image']);
        }
        if ($data['del'] === 1) {
            $assignProductData['image'] = '';
        }
        $model = $this->_items->create();

        if ($flag === 1) {
            $assignId   = $data['assign_id'];
            $assignData = $this->getAssignDataByAssignId($assignId);
            $assignData->getPrice();
            if ($assignData->getId() > 0) {
                $oldImage = $assignData->getImage();
                if ($oldImage !== $image && $image !== '') {
                    $assignProductData['image'] = $image;
                }
                $oldQty                = $assignData->getQty();
                $status                = $assignData->getStatus();
                $result['old_qty']     = $oldQty;
                $result['prev_status'] = $status;
                $result['flag']        = 1;
                unset($assignProductData['created_at']);
                if ($this->isEditApprovalRequired()) {
                    $result['status']            = 0;
                    $assignProductData['status'] = 0;
                }
            } else {
                return $result;
            }
            $model->addData($assignProductData)->setId($assignId)->save();
        } else {
            if ($this->isAddApprovalRequired()) {
                $result['status']            = 0;
                $assignProductData['status'] = 0;
            }
            $model->setData($assignProductData)->save();
        }
        $this->saveAdditionalAttributes($model, $product, $data);
        if ($model->getId() > 0) {
            $result['product_id'] = $productId;
            $result['qty']        = $qty;
            $result['assign_id']  = $model->getId();
        }

        return $result;
    }

    /**
     * Get Additional Attribute Value
     *
     * @param $assigned
     * @param $attributeId
     * @return string
     */
    public function getAdditionalAttributeValue($assigned, $attributeId): string
    {
        if (!$assigned) {
            return '';
        }
        $value    = '';
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $old_base = $this->_dataCollection->create()
            ->addFieldToFilter('type', $attributeId)
            ->addFieldToFilter('assign_id', $assigned->getId())
            ->addFieldToFilter('store_view', $store_id);
        if ($old_base->getSize()) {
            foreach ($old_base as $key) {
                $value = $key->getValue();
            }
        }
        return $value;
    }

    /**
     * Get Additional Attribute Raw
     *
     * @param $assigned
     * @param $attribute
     * @return string
     */
    public function getAdditionalAttributeValueRaw($assigned, $attribute): string
    {
        if (!$assigned) {
            return '';
        }
        $value    = '';
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $old_base = $this->_dataCollection->create()
            ->addFieldToFilter('type', $attribute['id'])
            ->addFieldToFilter('assign_id', $assigned->getId())
            ->addFieldToFilter('store_view', $store_id);
        if ($old_base->getSize()) {
            foreach ($old_base as $key) {
                $value = $key->getValue();
            }
        }
        if ($attribute['input_type'] === 'select') {
            foreach ($attribute['options'] as $option) {
                if ($value === $option['value']) {
                    $value = $option['label'];
                    break;
                }
            }
        }
        return $value;
    }

    /**
     * Get allowed attributes for given product
     *
     * @param Product $product
     * @return array
     */
    public function getAllowedAttributes(Product $product): array
    {
        $allowedAttributes = [];
        /** @var Product $product */

        $attributes = $product->getTypeInstance()->getSetAttributes($product);
        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $attrCode = $attribute->getAttributeCode();
            try {
                $backendAttribute = $attribute->getBackend()->getAttribute();
                if ($backendAttribute->getAllowSellersToSet() && !in_array($attrCode, $this->skipAttributes, true)) {
                    $frontendInput                               = $attribute->getFrontendInput();
                    $allowedAttributes[$attrCode]['input_type']  = $frontendInput;
                    $allowedAttributes[$attrCode]['is_required'] = $attribute->getIsRequired();
                    $allowedAttributes[$attrCode]['id']          = $attribute->getId();
                    $allowedAttributes[$attrCode]['code']        = $attrCode;
                    $allowedAttributes[$attrCode]['title']       = $attribute->getFrontendLabel();
                    $allowedAttributes[$attrCode]['label']       = __($attribute->getFrontendLabel());
                    switch ($frontendInput) {
                        case 'text':
                            break;
                        case 'select':
                            $attributeOptions                        = $attribute->getSource()->getAllOptions();
                            $allowedAttributes[$attrCode]['options'] = $attributeOptions;
                            break;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $allowedAttributes;
    }

    /**
     * Save Additional Attributes
     *
     * @param $model
     * @param Product $product
     * @param array $dataInput
     */
    public function saveAdditionalAttributes($model, Product $product, array $dataInput): void
    {
        $store_id   = $this->_storeManager->getStore()->getStoreId();
        $attributes = $product->getTypeInstance()->getSetAttributes($product);
        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $attrCode = $attribute->getAttributeCode();
            try {
                $backendAttribute = $attribute->getBackend()->getAttribute();
                if ($backendAttribute->getAllowSellersToSet()) {
                    $value    = $dataInput[$attrCode] ?? '';
                    $old_base = $this->_dataCollection->create()
                        ->addFieldToFilter('type', $attribute->getId())
                        ->addFieldToFilter('assign_id', $model->getId())
                        ->addFieldToFilter('store_view', $store_id);
                    if ($old_base->getSize()) {
                        foreach ($old_base as $key) {
                            $key->setValue($value)->save();
                        }
                    } else {
                        $data               = [];
                        $data['type']       = $attribute->getId();
                        $data['assign_id']  = $model->getId();
                        $data['value']      = $value;
                        $data['is_default'] = 0;
                        $data['status']     = 1;
                        $data['store_view'] = $store_id;
                        $this->_data->create()->setData($data)->save();
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    /**
     * Upload Images
     *
     * @param int $numberOfImages
     * @param int $assignId
     */
    public function uploadImages(int $numberOfImages, int $assignId): void
    {
        if ($numberOfImages > 0) {
            $uploadPath = $this->_filesystem
                ->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath('marketplace/assignproduct/product/');
            $uploadPath .= $assignId;
            $count      = 0;
            for ($i = 0; $i < $numberOfImages; $i++) {
                $count++;
                $fileId = 'showcase';
                $this->uploadImage($fileId, $uploadPath, $assignId, $count);
            }
        }
    }

    /**
     * get Description
     *
     * @param int $assignId
     * @return string
     */
    public function getDescription(int $assignId): string
    {
        $store_id   = $this->getStore()->getId();
        $desc       = '';
        $collection = $this->_data->create()->getCollection()
            ->addFieldToFilter('assign_id', $assignId)
            ->addFieldToFilter('is_default', 1)
            ->addFieldToFilter('type', 2)
            ->addFieldToFilter('store_view', $store_id);
        if ($collection->getSize()) {
            foreach ($collection as $key) {
                $desc = $key->getValue();
            }
        } else {
            $collection = $this->_data->create()->getCollection()
                ->addFieldToFilter('assign_id', $assignId)
                ->addFieldToFilter('is_default', 1)
                ->addFieldToFilter('type', 2);
            foreach ($collection as $key) {
                $desc = $key->getValue();
                break;
            }
        }
        if (!$desc) {
            $desc = $this->_items->create()->load($assignId)->getDescription();
        }
        return $desc;
    }

    /**
     * Check Product
     *
     * @param int $isAdd
     * @return array
     */
    public function checkProduct(int $isAdd = 0): array
    {
        $result   = ['msg' => '', 'error' => 0];
        $assignId = (int)$this->_request->getParam('id');
        if ($assignId === 0) {
            $result['error'] = 1;
            $result['msg']   = 'Invalid request.';
            return $result;
        }
        if ($isAdd === 1) {
            $productId = $assignId;
        } else {
            $assignData = $this->getAssignDataByAssignId($assignId);
            $productId  = $assignData->getProductId();
        }
        $product = $this->getProduct($productId);
        if ($product->getId() <= 0) {
            $result['error'] = 1;
            $result['msg']   = 'Product does not exist.';
            return $result;
        }
        $productType         = $product->getTypeId();
        $allowedProductTypes = $this->getAllowedProductTypes();
        if (!in_array($productType, $allowedProductTypes, true)) {
            $result['error'] = 1;
            $result['msg']   = 'Product type not allowed.';
            return $result;
        }
        $sellerId = $this->getSellerIdByProductId($productId);

        $customerId = $this->getCustomerId();
        if ($sellerId === $customerId) {
            $result['error'] = 1;
            $result['msg']   = 'Product is your own product.';
            return $result;
        }
        return $result;
    }

    /**
     * Get Current Format date
     *
     * @param string $format
     * @return string
     */
    public function getDateByFormat(string $format): string
    {
        return $this->dateTimeFactory->create()->gmtDate($format);
    }
}
