<?php

namespace App\Filament\Widgets;

use App\Services\BlockchainService;
use App\Settings\BlockchainSettings;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class GasWalletWidget extends ApexChartWidget
{
    protected static ?int $sort = 4;

    protected static ?string $chartId = 'gasWalletChart';

    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = null;

    public ?string $pollingInterval = '30s';

    protected function getHeading(): string
    {
        return __('p2p.widgets.gas_wallet.title');
    }

    protected function getOptions(): array
    {
        $settings = app(BlockchainSettings::class);
        $blockchain = app(BlockchainService::class);

        $weiString = '0';
        $ethValue = 0.0;
        $percentage = 0;

        if ($settings->gas_wallet_address) {
            try {
                $weiString = $blockchain->getEthBalance($settings->gas_wallet_address);
                // Convert wei to ETH (1 ETH = 1e18 wei)
                $ethValue = (float) bcdiv($weiString, '1000000000000000000', 18);
            } catch (\Throwable) {
                // Keep defaults on RPC failure
            }
        }

        $minBalance = (float) ($settings->min_gas_balance ?? 0.1);
        if ($minBalance > 0) {
            $percentage = min(round($ethValue / $minBalance * 100, 1), 100);
        }

        $ethLabel = number_format($ethValue, 4) . ' ETH';

        return [
            'chart' => [
                'type' => 'radialBar',
                'height' => 200,
                'toolbar' => ['show' => false],
            ],
            'series' => [$percentage],
            'labels' => [$ethLabel],
            'plotOptions' => [
                'radialBar' => [
                    'hollow' => ['size' => '60%'],
                    'dataLabels' => [
                        'name' => ['fontSize' => '12px', 'color' => '#9ca3af'],
                        'value' => [
                            'fontSize' => '20px',
                            'color' => '#f59e0b',
                        ],
                    ],
                ],
            ],
            'colors' => [$percentage < 30 ? '#ef4444' : ($percentage < 60 ? '#f59e0b' : '#10b981')],
            'tooltip' => ['theme' => 'dark'],
        ];
    }
}
