<?php

declare(strict_types=1);

namespace Rector\OpenApi;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Naming\Naming\UseImportsResolver;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Rector\Php80\NodeFactory\AttrGroupsFactory;
use Rector\Php80\NodeManipulator\AttributeGroupNamedArgumentManipulator;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Php80\ValueObject\DoctrineTagAndAnnotationToAttribute;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_merge;
use function str_starts_with;

final class OpenApiAnnotationToAttributeRector extends AbstractRector implements MinPhpVersionInterface
{
    public function __construct(
        private readonly AttrGroupsFactory $attrGroupsFactory,
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly AttributeGroupNamedArgumentManipulator $attributeGroupNamedArgumentManipulator,
        private readonly UseImportsResolver $useImportsResolver,
        private readonly PhpAttributeAnalyzer $phpAttributeAnalyzer,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change OpenApi annotation to attribute',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        /**
                         * @OA\Schema(schema="Order")
                         */
                        class Order {}
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        use OpenApi\Attributes as OA;
                        
                        #[OA\Schema(schema: 'Order')]
                        class Order {}
                        CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class, Property::class, Param::class, ClassMethod::class, Function_::class, Closure::class, ArrowFunction::class, Interface_::class];
    }

    /**
     * @param Class_|Property|Param|ClassMethod|Function_|Closure|ArrowFunction|Interface_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        $uses = $this->useImportsResolver->resolveBareUses();

        // 1. Process doctrine annotation classes
        $attributeGroups = $this->processDoctrineAnnotationClasses($phpDocInfo, $uses);
        if ($attributeGroups === []) {
            return null;
        }

        // 2. Add name to positional parameters
        $this->wrapPositionalParameters($attributeGroups);

        // 3. Reprint docblock
        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);
        $this->attributeGroupNamedArgumentManipulator->decorate($attributeGroups);
        $node->attrGroups = array_merge($node->attrGroups, $attributeGroups);

        return $node;
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::ATTRIBUTES;
    }

    /**
     * @param Use_[] $uses
     * @return AttributeGroup[]
     */
    private function processDoctrineAnnotationClasses(PhpDocInfo $phpDocInfo, array $uses): array
    {
        if ($phpDocInfo->getPhpDocNode()->children === []) {
            return [];
        }
        $doctrineTagAndAnnotationToAttributes = [];
        $doctrineTagValueNodes = [];
        foreach ($phpDocInfo->getPhpDocNode()->children as $phpDocChildNode) {
            if (!$phpDocChildNode instanceof PhpDocTagNode) {
                continue;
            }
            if (!$phpDocChildNode->value instanceof DoctrineAnnotationTagValueNode) {
                continue;
            }
            $doctrineTagValueNode = $phpDocChildNode->value;
            $annotationToAttribute = $this->matchAnnotationToAttribute($doctrineTagValueNode);
            if (!$annotationToAttribute instanceof AnnotationToAttribute) {
                continue;
            }
            $doctrineTagAndAnnotationToAttributes[] = new DoctrineTagAndAnnotationToAttribute($doctrineTagValueNode, $annotationToAttribute);
            $doctrineTagValueNodes[] = $doctrineTagValueNode;
        }
        $attributeGroups = $this->attrGroupsFactory->create($doctrineTagAndAnnotationToAttributes, $uses);
        if ($this->phpAttributeAnalyzer->hasRemoveArrayState($attributeGroups)) {
            return [];
        }
        foreach ($doctrineTagValueNodes as $doctrineTagValueNode) {
            $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $doctrineTagValueNode);
        }

        return $attributeGroups;
    }

    private function matchAnnotationToAttribute(DoctrineAnnotationTagValueNode $doctrineAnnotationTagValueNode): ?AnnotationToAttribute
    {
        /** @var string $resolvedAttributeClassName */
        $resolvedAttributeClassName = $doctrineAnnotationTagValueNode->identifierTypeNode->getAttribute(PhpDocAttributeKey::RESOLVED_CLASS);
        if (str_starts_with($resolvedAttributeClassName, 'OpenApi\Annotations')) {
            return new AnnotationToAttribute($resolvedAttributeClassName, str_replace('Annotations', 'Attributes', $resolvedAttributeClassName));
        }

        return null;
    }

    /**
     * @param AttributeGroup[] $attributeGroups
     */
    private function wrapPositionalParameters(array $attributeGroups): void
    {
        foreach ($attributeGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeArgs = $attribute->args;
                $args = $this->handleArgs($attributeArgs);

                $attribute->args = $args;
            }
        }
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $attributeArgs
     * @return list<Arg>
     */
    public function handleArgs(array $attributeArgs): array
    {
        $wrapAsArrayMap = [
            'Parameter' => 'parameters',
            'Property' => 'properties',
            'Response' => 'responses',
        ];

        $wrapAsSingleMap = [
            'Schema' => 'schema',
            'JsonContent' => 'content',
            'MediaType' => 'content',
            'Items' => 'items',
            'RequestBody' => 'requestBody',
            'AdditionalProperties' => 'additionalProperties',
        ];

        $args = [];
        $argsToWrapAsArray = [];
        $argsToWrapAsSingle = [];
        foreach ($attributeArgs as $attributeArg) {
            if ($attributeArg instanceof VariadicPlaceholder) {
                continue;
            }

            $value = $attributeArg->value;
            if ($value instanceof New_) {
                $value->args = $this->handleArgs($value->args);
            }

            if ($attributeArg->name === null && $value instanceof New_) {
                $class = $value->class;
                if (!$class instanceof Name) {
                    continue;
                }

                $last = $class->getLast();
                if (isset($wrapAsArrayMap[$last])) {
                    $argsToWrapAsArray[$wrapAsArrayMap[$last]][] = $attributeArg;
                    continue;
                }

                if (isset($wrapAsSingleMap[$last])) {
                    $argsToWrapAsSingle[$wrapAsSingleMap[$last]] = $attributeArg;
                    continue;
                }
            }
            $args[] = $attributeArg;
        }

        foreach ($argsToWrapAsSingle as $argName => $argToWrap) {
            $args[] = new Arg(
                $argToWrap->value,
                false,
                false,
                [],
                new Identifier($argName)
            );
        }

        foreach ($argsToWrapAsArray as $argName => $argGroup) {
            $arrayItems = [];
            foreach ($argGroup as $arg) {
                $arrayItems[] = new ArrayItem($arg->value);
            }

            $args[] = new Arg(
                new Array_($arrayItems),
                false,
                false,
                [],
                new Identifier($argName)
            );
        }

        return $args;
    }
}
