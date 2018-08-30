<?php

namespace Snowdog\CmsApi\Model;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Page;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Snowdog\CmsApi\Api\Data\PageInterfaceFactory;
use Snowdog\CmsApi\Api\Data\PageSearchResultsInterfaceFactory;
use Snowdog\CmsApi\Api\PageManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PageManager implements PageManagerInterface
{
    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var Page
     */
    private $pageResource;

    /**
     * @var Page\CollectionFactory
     */
    private $pageCollectionFactory;

    /**
     * @var PageSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var PageInterfaceFactory
     */
    private $pageDtoFactory;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @param PageRepositoryInterface $pageRepository
     * @param FilterProvider $filterProvider
     * @param PageFactory $pageFactory
     * @param Page $pageResource
     * @param Page\CollectionFactory $pageCollectionFactory
     * @param PageSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param PageInterfaceFactory $pageDtoFactory
     * @param DataObjectHelper $dataObjectHelper
     */
    public function __construct(
        PageRepositoryInterface $pageRepository,
        FilterProvider $filterProvider,
        PageFactory $pageFactory,
        Page $pageResource,
        Page\CollectionFactory $pageCollectionFactory,
        PageSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        PageInterfaceFactory $pageDtoFactory,
        DataObjectHelper $dataObjectHelper
    ) {
        $this->pageRepository = $pageRepository;
        $this->filterProvider = $filterProvider;
        $this->pageFactory = $pageFactory;
        $this->pageResource = $pageResource;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->pageDtoFactory = $pageDtoFactory;
        $this->dataObjectHelper = $dataObjectHelper;
    }

    /**
     * @inheritdoc
     */
    public function getById($pageId)
    {
        $page = $this->pageRepository->getById($pageId);
        $content = $this->getPageContentFiltered($page->getContent());
        $page->setContent($content);

        return $page;
    }

    /**
     * @inheritdoc
     */
    public function getByIdentifier($identifier, $storeId = null)
    {
        $page = $this->pageFactory->create();
        $page->setStoreId($storeId);
        $this->pageResource->load($page, $identifier, PageInterface::IDENTIFIER);

        if (!$page->getId()) {
            throw new NoSuchEntityException(
                __('CMS Page with identifier "%1" does not exist.', $identifier)
            );
        }

        $content = $this->getPageContentFiltered($page->getContent());
        $page->setContent($content);

        return $page;
    }

    /**
     * @inheritdoc
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Magento\Cms\Model\ResourceModel\Page\Collection $collection */
        $collection = $this->pageCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $items = [];
        /** @var \Magento\Cms\Model\Page $page */
        foreach ($collection->getItems() as $page) {
            $content = $this->getPageContentFiltered($page->getContent());
            $page->setContent($content);
            $pageDto = $this->pageDtoFactory->create();
            $this->dataObjectHelper->populateWithArray(
                $pageDto,
                $page->getData(),
                \Snowdog\CmsApi\Api\Data\PageInterface::class
            );
            $items[] = $pageDto;
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param string $content
     * @return string
     */
    private function getPageContentFiltered($content)
    {
        return $this->filterProvider->getPageFilter()
            ->filter($content);
    }
}
