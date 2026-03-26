<?php

declare(strict_types=1);

namespace Rector\OpenApi;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\UseUse;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Naming\Naming\UseImportsResolver;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class AddMissingOAAnnotationImportRector extends AbstractRector
{
    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly UseImportsResolver $useImportsResolver,
        private readonly UseNodesToAddCollector $useNodesToAddCollector,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add missing OA import', [new CodeSample(
            <<<'CODE_SAMPLE'
                /**
                 * @OA\Schema()
                 */
                class SchemaWithUnnamedParameterDto {}
                CODE_SAMPLE,
            <<<'CODE_SAMPLE'
                use OpenApi\Annotations as OA;

                /**
                 * @OA\Schema()
                 */
                class SchemaWithUnnamedParameterDto {}
                CODE_SAMPLE,
        )]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class, Property::class, Param::class, ClassMethod::class, Function_::class, Closure::class, ArrowFunction::class, Interface_::class, Trait_::class];
    }

    /**
     * @phpstan-param Class_|Property|Param|ClassMethod|Function_|Closure|ArrowFunction|Interface_|Trait_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // 1. Check if already imported then skip
        $uses = $this->useImportsResolver->resolveBareUses();
        foreach ($uses as $use) {
            $useUses = $use->uses;
            foreach ($useUses as $useUse) {
                if ($useUse->name->toString() === 'OpenApi\Annotations' || $useUse->alias?->name === 'OA') {
                    return null;
                }
           }
        }

        // 2. Check if has OA annotation then add import
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        if ($this->hasOpenApiUsageInAnnotations($phpDocInfo)) {
            $this->useNodesToAddCollector->addUseImport(new FullyQualifiedObjectType('OpenApi\Annotations as OA'));

            return $node;
        }

        return null;
    }

    private function hasOpenApiUsageInAnnotations(PhpDocInfo $phpDocInfo): bool
    {
        if ($phpDocInfo->getPhpDocNode()->children === []) {
            return false;
        }

        foreach ($phpDocInfo->getPhpDocNode()->children as $phpDocChildNode) {
            if (!$phpDocChildNode instanceof PhpDocTagNode) {
                continue;
            }
            if (!$phpDocChildNode->value instanceof DoctrineAnnotationTagValueNode) {
                continue;
            }
            $doctrineTagValueNode = $phpDocChildNode->value;
            if ($this->matchTagValueNode($doctrineTagValueNode)) {
                return true;
            }
        }

        return false;
    }

    private function matchTagValueNode(DoctrineAnnotationTagValueNode $doctrineTagValueNode): bool
    {
        return str_starts_with($doctrineTagValueNode->identifierTypeNode->name, '@OA\\');
    }
}
