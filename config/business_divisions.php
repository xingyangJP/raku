<?php

return [
    'default' => 'fifth_business',
    'options' => [
        'first_business' => [
            'label' => '第1種事業',
            'rate' => 0.90,
            'display_rate' => '90%',
            'description' => '卸売業（他の者から購入した商品をその性質・形状を変更しないで販売する事業）。',
        ],
        'second_business' => [
            'label' => '第2種事業',
            'rate' => 0.80,
            'display_rate' => '80%',
            'description' => '小売業（第1種事業を除く）や農林・漁業（飲食料品の譲渡を除く）。',
        ],
        'third_business' => [
            'label' => '第3種事業',
            'rate' => 0.70,
            'display_rate' => '70%',
            'description' => '農業・林業・漁業（飲食料品関連を含む）、鉱業、製造業、電気・ガス業など。',
        ],
        'fourth_business' => [
            'label' => '第4種事業',
            'rate' => 0.60,
            'display_rate' => '60%',
            'description' => '飲食店業を除く加工その他飲食料品以外の役務提供など。',
        ],
        'fifth_business' => [
            'label' => '第5種事業',
            'rate' => 0.50,
            'display_rate' => '50%',
            'description' => '運輸通信、金融、保険、サービス業（飲食店業を除く）。システム開発など。',
        ],
        'sixth_business' => [
            'label' => '第6種事業',
            'rate' => 0.40,
            'display_rate' => '40%',
            'description' => '不動産業。',
        ],
    ],
];
