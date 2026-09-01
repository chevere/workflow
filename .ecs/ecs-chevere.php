<?php

/*
 * This file is part of Chevere.
 *
 * (c) Rodolfo Berrios <rodolfo@chevere.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\NoAliasFunctionsFixer;
use PhpCsFixer\Fixer\Alias\NoAliasLanguageConstructCallFixer;
use PhpCsFixer\Fixer\Alias\NoMixedEchoPrintFixer;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ArrayNotation\NoMultilineWhitespaceAroundDoubleArrowFixer;
use PhpCsFixer\Fixer\ArrayNotation\NormalizeIndexBraceFixer;
use PhpCsFixer\Fixer\Casing\IntegerLiteralCaseFixer;
use PhpCsFixer\Fixer\Casing\LowercaseStaticReferenceFixer;
use PhpCsFixer\Fixer\Casing\MagicConstantCasingFixer;
use PhpCsFixer\Fixer\Casing\MagicMethodCasingFixer;
use PhpCsFixer\Fixer\Casing\NativeFunctionCasingFixer;
use PhpCsFixer\Fixer\Casing\NativeFunctionTypeDeclarationCasingFixer;
use PhpCsFixer\Fixer\CastNotation\NoShortBoolCastFixer;
use PhpCsFixer\Fixer\CastNotation\NoUnsetCastFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Comment\SingleLineCommentStyleFixer;
use PhpCsFixer\Fixer\ControlStructure\IncludeFixer;
use PhpCsFixer\Fixer\ControlStructure\NoAlternativeSyntaxFixer;
use PhpCsFixer\Fixer\FunctionNotation\LambdaNotUsedImportFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Import\SingleImportPerStatementFixer;
use PhpCsFixer\Fixer\LanguageConstruct\CombineConsecutiveUnsetsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\SingleSpaceAroundConstructFixer;
use PhpCsFixer\Fixer\ListNotation\ListSyntaxFixer;
use PhpCsFixer\Fixer\NamespaceNotation\CleanNamespaceFixer;
use PhpCsFixer\Fixer\Operator\NoSpaceAroundDoubleColonFixer;
use PhpCsFixer\Fixer\Operator\ObjectOperatorWithoutWhitespaceFixer;
use PhpCsFixer\Fixer\Operator\StandardizeNotEqualsFixer;
use PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer;
use PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\Whitespace\CompactNullableTypehintFixer;
use PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer;
use PhpCsFixer\Fixer\Whitespace\TypesSpacesFixer;
use Symplify\CodingStandard\Fixer\Commenting\ParamReturnAndVarTagMalformsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

$ecsConfigBuilder = ECSConfig::configure()
    ->withParallel()
    ->withSets([SetList::PSR_12, SetList::COMMON])
    ->withRules([
        TypesSpacesFixer::class,
        NoUselessReturnFixer::class,
        LinebreakAfterOpeningTagFixer::class,
        StandardizeNotEqualsFixer::class,
        NoSpaceAroundDoubleColonFixer::class,
        CleanNamespaceFixer::class,
        ListSyntaxFixer::class,
        SingleSpaceAroundConstructFixer::class,
        LambdaNotUsedImportFixer::class,
        NoAlternativeSyntaxFixer::class,
        NoUnsetCastFixer::class,
        NoShortBoolCastFixer::class,
        NativeFunctionTypeDeclarationCasingFixer::class,
        NativeFunctionCasingFixer::class,
        MagicMethodCasingFixer::class,
        MagicConstantCasingFixer::class,
        LowercaseStaticReferenceFixer::class,
        IntegerLiteralCaseFixer::class,
        NormalizeIndexBraceFixer::class,
        NoMultilineWhitespaceAroundDoubleArrowFixer::class,
        BlankLineBeforeStatementFixer::class,
        CombineConsecutiveUnsetsFixer::class,
        CompactNullableTypehintFixer::class,
        DeclareStrictTypesFixer::class,
        IncludeFixer::class,
        MultilineWhitespaceBeforeSemicolonsFixer::class,
        NoAliasFunctionsFixer::class,
        NoAliasLanguageConstructCallFixer::class,
        NoEmptyStatementFixer::class,
        NoMixedEchoPrintFixer::class,
        ObjectOperatorWithoutWhitespaceFixer::class,
        ParamReturnAndVarTagMalformsFixer::class,
        ReturnAssignmentFixer::class,
        SingleQuoteFixer::class,
    ])
    ->withConfiguredRule(
        SingleLineCommentStyleFixer::class,
        [
            'comment_types' => ['hash'],
        ]
    )
    ->withConfiguredRule(
        OrderedImportsFixer::class,
        [
            'imports_order' => ['class', 'function', 'const'],
        ]
    )
    ->withConfiguredRule(
        ArraySyntaxFixer::class,
        [
            'syntax' => 'short',
        ]
    )
    ->withConfiguredRule(
        NoExtraBlankLinesFixer::class,
        [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ]
    )
    ->withSkip(
        [
            dirname(__DIR__) . '/vendor',
            SingleImportPerStatementFixer::class => null,
        ]
    );
$headerFile = __DIR__ . '/.header';
if (file_exists($headerFile)) {
    $ecsConfigBuilder = $ecsConfigBuilder->withConfiguredRule(
        HeaderCommentFixer::class,
        [
            'header' => file_get_contents($headerFile),
            'location' => 'after_open',
        ]
    );
}

return $ecsConfigBuilder;
