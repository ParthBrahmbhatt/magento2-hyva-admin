<?php declare(strict_types=1);

namespace Hyva\Admin\Model\GridSourceType;

use Hyva\Admin\Model\GridSourcePrefetchEventDispatcher;
use Hyva\Admin\Model\GridSourceType\CollectionSourceType\GridSourceCollectionFactory;
use Hyva\Admin\Model\GridSourceType\Internal\RawGridSourceDataAccessor;
use Hyva\Admin\Model\RawGridSourceContainer;
use Hyva\Admin\Model\TypeReflection;
use Hyva\Admin\ViewModel\HyvaGrid\ColumnDefinitionInterface;
use Hyva\Admin\ViewModel\HyvaGrid\ColumnDefinitionInterfaceFactory;
use Magento\Eav\Model\Entity\Collection\AbstractCollection as AbstractEavCollection;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

use function array_filter as filter;
use function array_values as values;

class CollectionGridSourceType implements GridSourceTypeInterface
{
    private string $gridName;

    private array $sourceConfiguration;

    private TypeReflection $typeReflection;

    private RawGridSourceDataAccessor $gridSourceDataAccessor;

    private ColumnDefinitionInterfaceFactory $columnDefinitionFactory;

    private GridSourceCollectionFactory $gridSourceCollectionFactory;

    private CollectionProcessorInterface $defaultCollectionProcessor;

    private CollectionProcessorInterface $eavCollectionProcessor;

    private GridSourcePrefetchEventDispatcher $gridSourcePrefetchEventDispatcher;

    public function __construct(
        string $gridName,
        array $sourceConfiguration,
        TypeReflection $typeReflection,
        RawGridSourceDataAccessor $gridSourceDataAccessor,
        ColumnDefinitionInterfaceFactory $columnDefinitionFactory,
        GridSourceCollectionFactory $gridSourceCollectionFactory,
        CollectionProcessorInterface $defaultCollectionProcessor,
        CollectionProcessorInterface $eavCollectionProcessor,
        GridSourcePrefetchEventDispatcher $gridSourcePrefetchEventDispatcher
    ) {
        $this->gridName                          = $gridName;
        $this->sourceConfiguration               = $sourceConfiguration;
        $this->typeReflection                    = $typeReflection;
        $this->gridSourceDataAccessor            = $gridSourceDataAccessor;
        $this->columnDefinitionFactory           = $columnDefinitionFactory;
        $this->gridSourceCollectionFactory       = $gridSourceCollectionFactory;
        $this->defaultCollectionProcessor        = $defaultCollectionProcessor;
        $this->eavCollectionProcessor            = $eavCollectionProcessor;
        $this->gridSourcePrefetchEventDispatcher = $gridSourcePrefetchEventDispatcher;
    }

    private function getRecordType(): string
    {
        return $this->gridSourceCollectionFactory->create($this->getCollectionConfig())->getItemObjectClass();
    }

    private function getCollectionConfig(): string
    {
        return $this->sourceConfiguration['collection'] ?? '';
    }

    public function getColumnKeys(): array
    {
        return $this->typeReflection->getFieldNames($this->getRecordType());
    }

    public function getColumnDefinition(string $key): ColumnDefinitionInterface
    {
        return $this->buildColumnDefinition($key);
    }

    private function buildColumnDefinition(string $key): ColumnDefinitionInterface
    {
        $recordType = $this->getRecordType();
        $columnType = $this->typeReflection->getColumnType($recordType, $key);
        $label      = $this->typeReflection->extractLabel($recordType, $key);
        $options    = $this->typeReflection->extractOptions($recordType, $key);

        $sortable = $this->isNonSortableColumn($key, $recordType, $columnType)
            ? 'false'
            : null;

        $constructorArguments = filter([
            'key'      => $key,
            'type'     => $columnType,
            'label'    => $label,
            'options'  => $options,
            'sortable' => $sortable,
        ]);

        return $this->columnDefinitionFactory->create($constructorArguments);
    }

    private function isNonSortableColumn(string $key, string $recordType, string $columnType): bool
    {
        // Implement this as needed
        return false;
    }

    public function fetchData(SearchCriteriaInterface $searchCriteria): RawGridSourceContainer
    {
        $collection = $this->gridSourceCollectionFactory->create($this->getCollectionConfig());
        if (method_exists($collection, 'addAttributeToSelect')) {
            $collection->addAttributeToSelect('*');
        }

        $preprocessedSearchCriteria = $this->gridSourcePrefetchEventDispatcher->dispatch(
            $this->gridName,
            $this->getRecordType(),
            $searchCriteria
        );

        if (is_subclass_of($collection, AbstractEavCollection::class)) {
            $this->eavCollectionProcessor->process($preprocessedSearchCriteria, $collection);
        } else {
            $this->defaultCollectionProcessor->process($preprocessedSearchCriteria, $collection);
        }

        return RawGridSourceContainer::forData($collection);
    }

    public function extractRecords(RawGridSourceContainer $rawGridData): array
    {
        return values($this->gridSourceDataAccessor->unbox($rawGridData)->getItems());
    }

    public function extractValue($record, string $key)
    {
        return $this->typeReflection->extractValue($this->getRecordType(), $key, $record);
    }

    public function extractTotalRowCount(RawGridSourceContainer $rawGridData): int
    {
        return $this->gridSourceDataAccessor->unbox($rawGridData)->getSize();
    }
}
