<?php

/**
 * This file is part of jurager/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Jurager\Exchange1C\Services;

use Jurager\Exchange1C\Config;
use Jurager\Exchange1C\Events\AfterComplete;
use Jurager\Exchange1C\Events\AfterProductsSync;
use Jurager\Exchange1C\Events\AfterUpdateProduct;
use Jurager\Exchange1C\Events\BeforeProductsSync;
use Jurager\Exchange1C\Events\BeforeUpdateProduct;
use Jurager\Exchange1C\Exceptions\Exchange1CException;
use Jurager\Exchange1C\Interfaces\EventDispatcherInterface;
use Jurager\Exchange1C\Interfaces\GroupInterface;
use Jurager\Exchange1C\Interfaces\ModelBuilderInterface;
use Jurager\Exchange1C\Interfaces\ProductInterface;
use Symfony\Component\HttpFoundation\Request;
use Zenwalker\CommerceML\CommerceML;
use Zenwalker\CommerceML\Model\Product;

/**
 * Class SectionsService.
 */
class CategoryService
{
    /**
     * @var array Массив идентификаторов товаров которые были добавлены и обновлены
     */
    protected $_ids;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var ModelBuilderInterface
     */
    private $modelBuilder;

    /**
     * CategoryService constructor.
     *
     * @param Request                  $request
     * @param Config                   $config
     * @param EventDispatcherInterface $dispatcher
     * @param ModelBuilderInterface    $modelBuilder
     */
    public function __construct(Request $request, Config $config, EventDispatcherInterface $dispatcher, ModelBuilderInterface $modelBuilder)
    {
        $this->request = $request;
        $this->config = $config;
        $this->dispatcher = $dispatcher;
        $this->modelBuilder = $modelBuilder;
    }

    /**
     * Базовый метод запуска импорта.
     *
     * @throws Exchange1CException
     */
    public function import(): void
    {
        $filename = basename($this->request->get('filename'));

        $commerce = new CommerceML();
        $commerce->loadImportXml($this->config->getFullPath($filename));
        $classifierFile = $this->config->getFullPath('classifier.xml');
        if ($commerce->classifier->xml) {
            $commerce->classifier->xml->saveXML($classifierFile);
            if ($groupClass = $this->getGroupClass()) {
                $groupClass::createTree1c($commerce->classifier->getGroups());
            }
        } else {
            $commerce->classifier->xml = simplexml_load_string(file_get_contents($classifierFile));
            $this->beforeProductsSync();
            //$productClass::createProperties1c($commerce->classifier->getProperties());
            $productClass = $this->getProductClass();

            foreach ($commerce->catalog->getProducts() as $product) {
                if (!$model = $productClass::createModel1c($product, $this->config->getMerchant())) {
                    throw new Exchange1CException("Модель продукта не найдена, проверьте реализацию $productClass::createModel1c");
                }
                $this->parseProduct($model, $product);
                $this->_ids[] = $model->getPrimaryKey();
                $model = null;
                unset($model, $product);
                gc_collect_cycles();
            }
            $this->afterProductsSync();
        }
    }

    public function price(): void
    {
        $filename = basename($this->request->get('filename'));

        $commerce = new CommerceML();
        $commerce->loadImportXml($this->config->getFullPath($filename));
        $classifierFile = $this->config->getFullPath('classifier.xml');
        $commerce->classifier->xml = simplexml_load_string(file_get_contents($classifierFile));

        $productClass = $this->getProductClass();

        foreach ($commerce->importXml->ПакетПредложений->Предложения->Предложение as $offer) {
            $product = $productClass::findProductBy1c($offer->Ид->__toString());
            if ($product) {
                $product->updatePrice1c($offer->Цены->Цена);
            }
            unset($product);
            gc_collect_cycles();
        }
    }

    public function rest(): void
    {

        $filename = basename($this->request->get('filename'));

        $commerce = new CommerceML();
        $commerce->loadImportXml($this->config->getFullPath($filename));
        $classifierFile = $this->config->getFullPath('classifier.xml');
        $commerce->classifier->xml = simplexml_load_string(file_get_contents($classifierFile));

        $productClass = $this->getProductClass();

        foreach ($commerce->importXml->ПакетПредложений->Предложения->Предложение as $offer) {
            $product = $productClass::findProductBy1c($offer->Ид->__toString());
            if ($product) {
                $product->updateRest1c($offer->Остатки->Остаток);
            }
            unset($product);
            gc_collect_cycles();
        }
    }

    /**
     * @return GroupInterface|null
     */
    protected function getGroupClass(): ?GroupInterface
    {
        return $this->modelBuilder->getInterfaceClass($this->config, GroupInterface::class);
    }

    /**
     * @return ProductInterface|null
     */
    protected function getProductClass(): ?ProductInterface
    {
        return $this->modelBuilder->getInterfaceClass($this->config, ProductInterface::class);
    }

    /**
     * @param ProductInterface                    $model
     * @param \Zenwalker\CommerceML\Model\Product $product
     */
    protected function parseProduct(ProductInterface $model, Product $product): void
    {
        $this->beforeUpdateProduct($model);
        $model->setRaw1cData($product->owner, $product);
        $this->parseGroups($model, $product);
        $this->parseProperties($model, $product);
        $this->parseRequisites($model, $product);
        $this->parseImage($model, $product);
        $this->afterUpdateProduct($model);

        unset($group);
    }

    /**
     * @param ProductInterface $model
     * @param Product          $product
     */
    protected function parseGroups(ProductInterface $model, Product $product): void
    {
        $group = $product->getGroup();
        $model->setGroup1c($group);
    }

    /**
     * @param ProductInterface $model
     * @param Product          $product
     */
    protected function parseProperties(ProductInterface $model, Product $product): void
    {
        foreach ($product->getProperties() as $property) {
            $model->setProperty1c($property);
        }
    }

    /**
     * @param ProductInterface $model
     * @param Product          $product
     */
    protected function parseRequisites(ProductInterface $model, Product $product): void
    {
        $requisites = $product->getRequisites();
        foreach ($requisites as $requisite) {
            $model->setRequisite1c($requisite->name, $requisite->value);
        }
    }

    /**
     * @param ProductInterface $model
     * @param Product          $product
     */
    protected function parseImage(ProductInterface $model, Product $product)
    {
        $images = $product->getImages();
        foreach ($images as $image) {
            $path = $this->config->getFullPath(basename($image->path));
            if (file_exists($path)) {
                $model->addImage1c($path, $image->caption);
            }
        }
    }

    protected function beforeProductsSync(): void
    {
        $event = new BeforeProductsSync();
        $this->dispatcher->dispatch($event);
    }

    protected function afterProductsSync(): void
    {
        $event = new AfterProductsSync($this->_ids, $this->config->getMerchant());
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param ProductInterface $model
     */
    protected function beforeUpdateProduct(ProductInterface $model): void
    {
        $event = new BeforeUpdateProduct($model);
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param ProductInterface $model
     */
    protected function afterUpdateProduct(ProductInterface $model): void
    {
        $event = new AfterUpdateProduct($model);
        $this->dispatcher->dispatch($event);
    }

    public function afterComplete(): void
    {
        $event = new AfterComplete($this->config->getMerchant());
        $this->dispatcher->dispatch($event);
    }
}
