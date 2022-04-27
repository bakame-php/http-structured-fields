<?php 

declare(strict_types=1);

use Rector\Core\Configuration\Option;

use Rector\DowngradePhp80\Rector\Catch_\DowngradeNonCapturingCatchesRector;
use Rector\DowngradePhp80\Rector\Class_\DowngradePropertyPromotionRector;
use Rector\DowngradePhp80\Rector\ClassMethod\DowngradeStaticTypeDeclarationRector;
use Rector\DowngradePhp80\Rector\ClassMethod\DowngradeTrailingCommasInParamUseRector;
use Rector\DowngradePhp80\Rector\Expression\DowngradeMatchToSwitchRector;
use Rector\DowngradePhp80\Rector\Expression\DowngradeThrowExprRector;
use Rector\DowngradePhp80\Rector\FuncCall\DowngradeStrContainsRector;
use Rector\DowngradePhp80\Rector\FunctionLike\DowngradeUnionTypeDeclarationRector;
use Rector\DowngradePhp80\Rector\NullsafeMethodCall\DowngradeNullsafeToTernaryOperatorRector;
use Rector\DowngradePhp80\Rector\Property\DowngradeUnionTypeTypedPropertyRector;

use Rector\DowngradePhp81\Rector\Array_\DowngradeArraySpreadStringKeyRector;
use Rector\DowngradePhp81\Rector\FuncCall\DowngradeArrayIsListRector;
use Rector\DowngradePhp81\Rector\FuncCall\DowngradeFirstClassCallableSyntaxRector;
use Rector\DowngradePhp81\Rector\FunctionLike\DowngradeNewInInitializerRector;
use Rector\DowngradePhp81\Rector\Property\DowngradeReadonlyPropertyRector;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {

	$parameters = $containerConfigurator->parameters();

	$parameters->set(Option::PATHS, __DIR__.'/src');
	$parameters->set(Option::PHP_VERSION_FEATURES, 70400);

	$services = $containerConfigurator->services();

    $services->set(DowngradeMatchToSwitchRector::class);
    $services->set(DowngradeNonCapturingCatchesRector::class);
    $services->set(DowngradeNullsafeToTernaryOperatorRector::class);
    $services->set(DowngradePropertyPromotionRector::class);
    $services->set(DowngradeStaticTypeDeclarationRector::class);
    $services->set(DowngradeStrContainsRector::class);
    $services->set(DowngradeThrowExprRector::class);
    $services->set(DowngradeTrailingCommasInParamUseRector::class);
    $services->set(DowngradeUnionTypeDeclarationRector::class);
    $services->set(DowngradeUnionTypeTypedPropertyRector::class);

    $services->set(DowngradeArrayIsListRector::class);
    $services->set(DowngradeArraySpreadStringKeyRector::class);
    $services->set(DowngradeFirstClassCallableSyntaxRector::class);
    $services->set(DowngradeNewInInitializerRector::class);
    $services->set(DowngradeReadonlyPropertyRector::class);
};