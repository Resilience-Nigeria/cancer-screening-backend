<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaCoordinatesSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [

            // ================================================================
            // ABIA STATE
            // ================================================================

            // Aba North
            ['state' => 'Abia', 'lga' => 'Aba North', 'area' => 'Ariaria',          'latitude' =>  5.1167, 'longitude' =>  7.3667],
            ['state' => 'Abia', 'lga' => 'Aba North', 'area' => 'Eziukwu',          'latitude' =>  5.1167, 'longitude' =>  7.3833],
            ['state' => 'Abia', 'lga' => 'Aba North', 'area' => 'Ogbor Hill',       'latitude' =>  5.1333, 'longitude' =>  7.3667],
            ['state' => 'Abia', 'lga' => 'Aba North', 'area' => 'Ndiegoro',         'latitude' =>  5.1167, 'longitude' =>  7.3500],
            ['state' => 'Abia', 'lga' => 'Aba North', 'area' => 'Umuola',           'latitude' =>  5.1000, 'longitude' =>  7.3667],

            // Aba South
            ['state' => 'Abia', 'lga' => 'Aba South', 'area' => 'Aba Township',     'latitude' =>  5.0833, 'longitude' =>  7.3667],
            ['state' => 'Abia', 'lga' => 'Aba South', 'area' => 'Ekeoha',           'latitude' =>  5.0833, 'longitude' =>  7.3500],
            ['state' => 'Abia', 'lga' => 'Aba South', 'area' => 'Osisioma',         'latitude' =>  5.1000, 'longitude' =>  7.3000],
            ['state' => 'Abia', 'lga' => 'Aba South', 'area' => 'Owerrinta',        'latitude' =>  5.0667, 'longitude' =>  7.3167],

            // Umuahia North
            ['state' => 'Abia', 'lga' => 'Umuahia North', 'area' => 'Umuahia',      'latitude' =>  5.5333, 'longitude' =>  7.4833],
            ['state' => 'Abia', 'lga' => 'Umuahia North', 'area' => 'Ibeku',        'latitude' =>  5.5500, 'longitude' =>  7.5000],
            ['state' => 'Abia', 'lga' => 'Umuahia North', 'area' => 'Ohuhu',        'latitude' =>  5.5167, 'longitude' =>  7.5167],

            // Umuahia South
            ['state' => 'Abia', 'lga' => 'Umuahia South', 'area' => 'Olokoro',      'latitude' =>  5.4833, 'longitude' =>  7.4667],
            ['state' => 'Abia', 'lga' => 'Umuahia South', 'area' => 'Ubakala',      'latitude' =>  5.4667, 'longitude' =>  7.4500],

            // ================================================================
            // ADAMAWA STATE
            // ================================================================

            // Yola North
            ['state' => 'Adamawa', 'lga' => 'Yola North', 'area' => 'Yola',         'latitude' =>  9.2035, 'longitude' => 12.4954],
            ['state' => 'Adamawa', 'lga' => 'Yola North', 'area' => 'Jimeta',       'latitude' =>  9.2833, 'longitude' => 12.4667],
            ['state' => 'Adamawa', 'lga' => 'Yola North', 'area' => 'Doubeli',      'latitude' =>  9.2167, 'longitude' => 12.5000],

            // Yola South
            ['state' => 'Adamawa', 'lga' => 'Yola South', 'area' => 'Karewa',       'latitude' =>  9.1833, 'longitude' => 12.5000],
            ['state' => 'Adamawa', 'lga' => 'Yola South', 'area' => 'Mbamba',       'latitude' =>  9.1500, 'longitude' => 12.4833],
            ['state' => 'Adamawa', 'lga' => 'Yola South', 'area' => 'Bekaji',       'latitude' =>  9.1667, 'longitude' => 12.5167],

            // Mubi North
            ['state' => 'Adamawa', 'lga' => 'Mubi North', 'area' => 'Mubi',         'latitude' => 10.2667, 'longitude' => 13.2667],
            ['state' => 'Adamawa', 'lga' => 'Mubi North', 'area' => 'Muchalla',     'latitude' => 10.2833, 'longitude' => 13.2500],

            // ================================================================
            // AKWA IBOM STATE
            // ================================================================

            // Uyo
            ['state' => 'Akwa Ibom', 'lga' => 'Uyo', 'area' => 'Uyo Township',     'latitude' =>  5.0500, 'longitude' =>  7.9333],
            ['state' => 'Akwa Ibom', 'lga' => 'Uyo', 'area' => 'Ewet Housing',     'latitude' =>  5.0333, 'longitude' =>  7.9167],
            ['state' => 'Akwa Ibom', 'lga' => 'Uyo', 'area' => 'Itiam Etoi',       'latitude' =>  5.0167, 'longitude' =>  7.9500],
            ['state' => 'Akwa Ibom', 'lga' => 'Uyo', 'area' => 'Use Offot',        'latitude' =>  5.0667, 'longitude' =>  7.9167],
            ['state' => 'Akwa Ibom', 'lga' => 'Uyo', 'area' => 'Ikot Ekpene Road', 'latitude' =>  5.0833, 'longitude' =>  7.9000],

            // Eket
            ['state' => 'Akwa Ibom', 'lga' => 'Eket', 'area' => 'Eket Township',   'latitude' =>  4.6500, 'longitude' =>  7.9333],
            ['state' => 'Akwa Ibom', 'lga' => 'Eket', 'area' => 'Nung Udoe',       'latitude' =>  4.6333, 'longitude' =>  7.9167],
            ['state' => 'Akwa Ibom', 'lga' => 'Eket', 'area' => 'Afaha Eket',      'latitude' =>  4.6167, 'longitude' =>  7.9500],

            // Ikot Ekpene
            ['state' => 'Akwa Ibom', 'lga' => 'Ikot Ekpene', 'area' => 'Ikot Ekpene Township', 'latitude' => 5.1833, 'longitude' => 7.7167],
            ['state' => 'Akwa Ibom', 'lga' => 'Ikot Ekpene', 'area' => 'Afaha Obong',          'latitude' => 5.1667, 'longitude' => 7.7000],

            // Oron
            ['state' => 'Akwa Ibom', 'lga' => 'Oron', 'area' => 'Oron Township',   'latitude' =>  4.8000, 'longitude' =>  8.2333],
            ['state' => 'Akwa Ibom', 'lga' => 'Oron', 'area' => 'Iwuochang',       'latitude' =>  4.8167, 'longitude' =>  8.2167],

            // ================================================================
            // ANAMBRA STATE
            // ================================================================

            // Awka South
            ['state' => 'Anambra', 'lga' => 'Awka South', 'area' => 'Awka',         'latitude' =>  6.2093, 'longitude' =>  7.0731],
            ['state' => 'Anambra', 'lga' => 'Awka South', 'area' => 'Amawbia',      'latitude' =>  6.1833, 'longitude' =>  7.0667],
            ['state' => 'Anambra', 'lga' => 'Awka South', 'area' => 'Agu-Awka',     'latitude' =>  6.2167, 'longitude' =>  7.0833],
            ['state' => 'Anambra', 'lga' => 'Awka South', 'area' => 'Nibo',         'latitude' =>  6.1667, 'longitude' =>  7.0500],

            // Awka North
            ['state' => 'Anambra', 'lga' => 'Awka North', 'area' => 'Achalla',      'latitude' =>  6.3167, 'longitude' =>  7.0333],
            ['state' => 'Anambra', 'lga' => 'Awka North', 'area' => 'Mgbakwu',      'latitude' =>  6.3000, 'longitude' =>  7.0500],

            // Onitsha North
            ['state' => 'Anambra', 'lga' => 'Onitsha North', 'area' => 'Onitsha Main Market', 'latitude' => 6.1600, 'longitude' => 6.7900],
            ['state' => 'Anambra', 'lga' => 'Onitsha North', 'area' => 'GRA Onitsha',         'latitude' => 6.1700, 'longitude' => 6.7700],
            ['state' => 'Anambra', 'lga' => 'Onitsha North', 'area' => 'Fegge',               'latitude' => 6.1533, 'longitude' => 6.7867],

            // Onitsha South
            ['state' => 'Anambra', 'lga' => 'Onitsha South', 'area' => 'Inland Town',         'latitude' => 6.1400, 'longitude' => 6.7833],
            ['state' => 'Anambra', 'lga' => 'Onitsha South', 'area' => 'Woliwo',              'latitude' => 6.1333, 'longitude' => 6.7667],

            // Nnewi North
            ['state' => 'Anambra', 'lga' => 'Nnewi North', 'area' => 'Nnewi',       'latitude' =>  6.0170, 'longitude' =>  6.9195],
            ['state' => 'Anambra', 'lga' => 'Nnewi North', 'area' => 'Otolo',       'latitude' =>  6.0333, 'longitude' =>  6.9333],
            ['state' => 'Anambra', 'lga' => 'Nnewi North', 'area' => 'Uruagu',      'latitude' =>  6.0500, 'longitude' =>  6.9167],

            // ================================================================
            // BAUCHI STATE
            // ================================================================

            // Bauchi
            ['state' => 'Bauchi', 'lga' => 'Bauchi', 'area' => 'Bauchi Township',   'latitude' => 10.3108, 'longitude' =>  9.8436],
            ['state' => 'Bauchi', 'lga' => 'Bauchi', 'area' => 'Muda Lawal',        'latitude' => 10.3167, 'longitude' =>  9.8333],
            ['state' => 'Bauchi', 'lga' => 'Bauchi', 'area' => 'Wunti',             'latitude' => 10.3000, 'longitude' =>  9.8500],
            ['state' => 'Bauchi', 'lga' => 'Bauchi', 'area' => 'Railway',           'latitude' => 10.3167, 'longitude' =>  9.8667],
            ['state' => 'Bauchi', 'lga' => 'Bauchi', 'area' => 'Makama',            'latitude' => 10.3000, 'longitude' =>  9.8167],

            // ================================================================
            // BAYELSA STATE
            // ================================================================

            // Yenagoa
            ['state' => 'Bayelsa', 'lga' => 'Yenagoa', 'area' => 'Yenagoa Township','latitude' =>  4.9247, 'longitude' =>  6.2642],
            ['state' => 'Bayelsa', 'lga' => 'Yenagoa', 'area' => 'Kpansia',         'latitude' =>  4.9333, 'longitude' =>  6.2500],
            ['state' => 'Bayelsa', 'lga' => 'Yenagoa', 'area' => 'Okutukutu',       'latitude' =>  4.9167, 'longitude' =>  6.2833],
            ['state' => 'Bayelsa', 'lga' => 'Yenagoa', 'area' => 'Biogbolo',        'latitude' =>  4.9500, 'longitude' =>  6.2667],
            ['state' => 'Bayelsa', 'lga' => 'Yenagoa', 'area' => 'Amarata',         'latitude' =>  4.9167, 'longitude' =>  6.2500],

            // ================================================================
            // BENUE STATE
            // ================================================================

            // Makurdi
            ['state' => 'Benue', 'lga' => 'Makurdi', 'area' => 'Makurdi Township',  'latitude' =>  7.7314, 'longitude' =>  8.5370],
            ['state' => 'Benue', 'lga' => 'Makurdi', 'area' => 'North Bank',        'latitude' =>  7.7500, 'longitude' =>  8.5167],
            ['state' => 'Benue', 'lga' => 'Makurdi', 'area' => 'High Level',        'latitude' =>  7.7167, 'longitude' =>  8.5500],
            ['state' => 'Benue', 'lga' => 'Makurdi', 'area' => 'Wadata',            'latitude' =>  7.7333, 'longitude' =>  8.5333],
            ['state' => 'Benue', 'lga' => 'Makurdi', 'area' => 'GRA Makurdi',       'latitude' =>  7.7500, 'longitude' =>  8.5333],

            // Gboko
            ['state' => 'Benue', 'lga' => 'Gboko', 'area' => 'Gboko Township',      'latitude' =>  7.3167, 'longitude' =>  9.0000],
            ['state' => 'Benue', 'lga' => 'Gboko', 'area' => 'Yandev',              'latitude' =>  7.3333, 'longitude' =>  9.0167],

            // ================================================================
            // BORNO STATE
            // ================================================================

            // Maiduguri
            ['state' => 'Borno', 'lga' => 'Maiduguri', 'area' => 'Maiduguri Township', 'latitude' => 11.8333, 'longitude' => 13.1500],
            ['state' => 'Borno', 'lga' => 'Maiduguri', 'area' => 'GRA Maiduguri',      'latitude' => 11.8500, 'longitude' => 13.1333],
            ['state' => 'Borno', 'lga' => 'Maiduguri', 'area' => 'Gwange',             'latitude' => 11.8167, 'longitude' => 13.1500],
            ['state' => 'Borno', 'lga' => 'Maiduguri', 'area' => 'Gamboru',            'latitude' => 11.8333, 'longitude' => 13.1667],
            ['state' => 'Borno', 'lga' => 'Maiduguri', 'area' => 'Bulumkutu',          'latitude' => 11.8000, 'longitude' => 13.1333],

            // Jere
            ['state' => 'Borno', 'lga' => 'Jere', 'area' => 'Jere',                'latitude' => 11.7500, 'longitude' => 13.1833],
            ['state' => 'Borno', 'lga' => 'Jere', 'area' => 'Mele',                'latitude' => 11.7667, 'longitude' => 13.2000],
            ['state' => 'Borno', 'lga' => 'Jere', 'area' => 'Kayamla',             'latitude' => 11.7333, 'longitude' => 13.1667],

            // ================================================================
            // CROSS RIVER STATE
            // ================================================================

            // Calabar Municipal
            ['state' => 'Cross River', 'lga' => 'Calabar Municipal', 'area' => 'Calabar Township', 'latitude' => 4.9500, 'longitude' => 8.3333],
            ['state' => 'Cross River', 'lga' => 'Calabar Municipal', 'area' => 'Duke Town',         'latitude' => 4.9500, 'longitude' => 8.3167],
            ['state' => 'Cross River', 'lga' => 'Calabar Municipal', 'area' => 'Henshaw Town',      'latitude' => 4.9667, 'longitude' => 8.3333],
            ['state' => 'Cross River', 'lga' => 'Calabar Municipal', 'area' => 'Atamunu',           'latitude' => 4.9333, 'longitude' => 8.3167],

            // Calabar South
            ['state' => 'Cross River', 'lga' => 'Calabar South', 'area' => 'Diamond Hill',  'latitude' => 4.9333, 'longitude' => 8.3167],
            ['state' => 'Cross River', 'lga' => 'Calabar South', 'area' => 'Ikot Ansa',     'latitude' => 4.9167, 'longitude' => 8.3167],
            ['state' => 'Cross River', 'lga' => 'Calabar South', 'area' => 'Esuk Utan',     'latitude' => 4.9167, 'longitude' => 8.3333],

            // Ogoja
            ['state' => 'Cross River', 'lga' => 'Ogoja', 'area' => 'Ogoja Township',  'latitude' =>  6.6500, 'longitude' =>  8.8000],
            ['state' => 'Cross River', 'lga' => 'Ogoja', 'area' => 'Ikom Road',       'latitude' =>  6.6333, 'longitude' =>  8.8167],

            // ================================================================
            // DELTA STATE
            // ================================================================

            // Warri South
            ['state' => 'Delta', 'lga' => 'Warri South', 'area' => 'Warri Township',  'latitude' =>  5.5167, 'longitude' =>  5.7500],
            ['state' => 'Delta', 'lga' => 'Warri South', 'area' => 'Effurun',         'latitude' =>  5.5500, 'longitude' =>  5.7833],
            ['state' => 'Delta', 'lga' => 'Warri South', 'area' => 'Okere',           'latitude' =>  5.5000, 'longitude' =>  5.7333],
            ['state' => 'Delta', 'lga' => 'Warri South', 'area' => 'GRA Warri',       'latitude' =>  5.5333, 'longitude' =>  5.7500],
            ['state' => 'Delta', 'lga' => 'Warri South', 'area' => 'Igbudu',          'latitude' =>  5.5167, 'longitude' =>  5.7667],

            // Uvwie
            ['state' => 'Delta', 'lga' => 'Uvwie', 'area' => 'Effurun',              'latitude' =>  5.5500, 'longitude' =>  5.7833],
            ['state' => 'Delta', 'lga' => 'Uvwie', 'area' => 'Ugbomro',              'latitude' =>  5.5667, 'longitude' =>  5.7833],
            ['state' => 'Delta', 'lga' => 'Uvwie', 'area' => 'PTI Road',             'latitude' =>  5.5333, 'longitude' =>  5.8000],

            // Oshimili South
            ['state' => 'Delta', 'lga' => 'Oshimili South', 'area' => 'Asaba',        'latitude' =>  6.1833, 'longitude' =>  6.7500],
            ['state' => 'Delta', 'lga' => 'Oshimili South', 'area' => 'Summit',       'latitude' =>  6.2000, 'longitude' =>  6.7333],
            ['state' => 'Delta', 'lga' => 'Oshimili South', 'area' => 'GRA Asaba',    'latitude' =>  6.1833, 'longitude' =>  6.7667],
            ['state' => 'Delta', 'lga' => 'Oshimili South', 'area' => 'Cable Point',  'latitude' =>  6.2000, 'longitude' =>  6.7500],

            // Sapele
            ['state' => 'Delta', 'lga' => 'Sapele', 'area' => 'Sapele Township',     'latitude' =>  5.8833, 'longitude' =>  5.6833],
            ['state' => 'Delta', 'lga' => 'Sapele', 'area' => 'Okirigho',            'latitude' =>  5.9000, 'longitude' =>  5.7000],

            // ================================================================
            // EBONYI STATE
            // ================================================================

            // Abakaliki
            ['state' => 'Ebonyi', 'lga' => 'Abakaliki', 'area' => 'Abakaliki Township', 'latitude' => 6.3249, 'longitude' => 8.1137],
            ['state' => 'Ebonyi', 'lga' => 'Abakaliki', 'area' => 'Kpirikpiri',         'latitude' => 6.3333, 'longitude' => 8.1000],
            ['state' => 'Ebonyi', 'lga' => 'Abakaliki', 'area' => 'Mile 50',            'latitude' => 6.3167, 'longitude' => 8.1167],
            ['state' => 'Ebonyi', 'lga' => 'Abakaliki', 'area' => 'Waterworks',         'latitude' => 6.3167, 'longitude' => 8.1333],

            // ================================================================
            // EDO STATE
            // ================================================================

            // Oredo
            ['state' => 'Edo', 'lga' => 'Oredo', 'area' => 'Benin City',            'latitude' =>  6.3350, 'longitude' =>  5.6278],
            ['state' => 'Edo', 'lga' => 'Oredo', 'area' => 'GRA Benin',             'latitude' =>  6.3500, 'longitude' =>  5.6167],
            ['state' => 'Edo', 'lga' => 'Oredo', 'area' => 'Ikpoba Hill',           'latitude' =>  6.3333, 'longitude' =>  5.6333],
            ['state' => 'Edo', 'lga' => 'Oredo', 'area' => 'New Benin',             'latitude' =>  6.3500, 'longitude' =>  5.6333],
            ['state' => 'Edo', 'lga' => 'Oredo', 'area' => 'Ugbowo',               'latitude' =>  6.3667, 'longitude' =>  5.6167],
            ['state' => 'Edo', 'lga' => 'Oredo', 'area' => 'Aduwawa',              'latitude' =>  6.3333, 'longitude' =>  5.6500],

            // Ikpoba Okha
            ['state' => 'Edo', 'lga' => 'Ikpoba Okha', 'area' => 'Ikpoba Hill',    'latitude' =>  6.2833, 'longitude' =>  5.6500],
            ['state' => 'Edo', 'lga' => 'Ikpoba Okha', 'area' => 'Egba',           'latitude' =>  6.2667, 'longitude' =>  5.6667],
            ['state' => 'Edo', 'lga' => 'Ikpoba Okha', 'area' => 'Airport Road',   'latitude' =>  6.2500, 'longitude' =>  5.5833],

            // Egor
            ['state' => 'Edo', 'lga' => 'Egor', 'area' => 'Uselu',                 'latitude' =>  6.3500, 'longitude' =>  5.6000],
            ['state' => 'Edo', 'lga' => 'Egor', 'area' => 'Ugbowo',               'latitude' =>  6.3667, 'longitude' =>  5.6167],
            ['state' => 'Edo', 'lga' => 'Egor', 'area' => 'Osagie',               'latitude' =>  6.3500, 'longitude' =>  5.5833],

            // ================================================================
            // EKITI STATE
            // ================================================================

            // Ado Ekiti
            ['state' => 'Ekiti', 'lga' => 'Ado Ekiti', 'area' => 'Ado Ekiti Township', 'latitude' => 7.6233, 'longitude' => 5.2211],
            ['state' => 'Ekiti', 'lga' => 'Ado Ekiti', 'area' => 'Basiri',             'latitude' => 7.6167, 'longitude' => 5.2333],
            ['state' => 'Ekiti', 'lga' => 'Ado Ekiti', 'area' => 'Okesa',             'latitude' => 7.6333, 'longitude' => 5.2167],
            ['state' => 'Ekiti', 'lga' => 'Ado Ekiti', 'area' => 'Ajilosun',          'latitude' => 7.6333, 'longitude' => 5.2333],

            // ================================================================
            // ENUGU STATE
            // ================================================================

            // Enugu North
            ['state' => 'Enugu', 'lga' => 'Enugu North', 'area' => 'Enugu Township',  'latitude' =>  6.4698, 'longitude' =>  7.5361],
            ['state' => 'Enugu', 'lga' => 'Enugu North', 'area' => 'Trans-Ekulu',     'latitude' =>  6.4833, 'longitude' =>  7.5167],
            ['state' => 'Enugu', 'lga' => 'Enugu North', 'area' => 'Achara Layout',   'latitude' =>  6.4500, 'longitude' =>  7.5167],
            ['state' => 'Enugu', 'lga' => 'Enugu North', 'area' => 'Independence Layout', 'latitude' => 6.4667, 'longitude' => 7.5167],
            ['state' => 'Enugu', 'lga' => 'Enugu North', 'area' => 'GRA Enugu',       'latitude' =>  6.4833, 'longitude' =>  7.5333],

            // Enugu South
            ['state' => 'Enugu', 'lga' => 'Enugu South', 'area' => 'Ogui',           'latitude' =>  6.4333, 'longitude' =>  7.5167],
            ['state' => 'Enugu', 'lga' => 'Enugu South', 'area' => 'Asata',          'latitude' =>  6.4500, 'longitude' =>  7.5333],
            ['state' => 'Enugu', 'lga' => 'Enugu South', 'area' => 'New Haven',      'latitude' =>  6.4167, 'longitude' =>  7.5000],
            ['state' => 'Enugu', 'lga' => 'Enugu South', 'area' => 'Maryland',       'latitude' =>  6.4000, 'longitude' =>  7.5167],

            // Nsukka
            ['state' => 'Enugu', 'lga' => 'Nsukka', 'area' => 'Nsukka Township',    'latitude' =>  6.8574, 'longitude' =>  7.3954],
            ['state' => 'Enugu', 'lga' => 'Nsukka', 'area' => 'University Road',    'latitude' =>  6.8667, 'longitude' =>  7.4000],
            ['state' => 'Enugu', 'lga' => 'Nsukka', 'area' => 'Ede-Oballa',         'latitude' =>  6.8500, 'longitude' =>  7.4167],

            // ================================================================
            // FCT / ABUJA
            // ================================================================

            // Abuja Municipal (AMAC)
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Garki',              'latitude' =>  8.8938, 'longitude' =>  7.1874],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Wuse',               'latitude' =>  9.0579, 'longitude' =>  7.4951],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Wuse 2',             'latitude' =>  9.0600, 'longitude' =>  7.4900],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Maitama',            'latitude' =>  9.0765, 'longitude' =>  7.4943],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Asokoro',            'latitude' =>  8.9892, 'longitude' =>  7.5328],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Gwarinpa',           'latitude' =>  9.1167, 'longitude' =>  7.4167],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Lugbe',              'latitude' =>  8.9833, 'longitude' =>  7.3667],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Jikwoyi',            'latitude' =>  8.9971, 'longitude' =>  7.3940],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Nyanya',             'latitude' =>  8.9833, 'longitude' =>  7.4500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Karu',               'latitude' =>  8.9833, 'longitude' =>  7.4667],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Kubwa',              'latitude' =>  9.1167, 'longitude' =>  7.3333],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Galadimawa',         'latitude' =>  8.9667, 'longitude' =>  7.3833],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Lokogoma',           'latitude' =>  8.9500, 'longitude' =>  7.3667],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Apo',                'latitude' =>  8.9500, 'longitude' =>  7.4833],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Wuye',               'latitude' =>  9.0667, 'longitude' =>  7.4500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Dutse',              'latitude' =>  9.0833, 'longitude' =>  7.3167],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Kabusa',             'latitude' =>  8.9167, 'longitude' =>  7.3833],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Gwagwa',             'latitude' =>  9.1000, 'longitude' =>  7.3500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Gudu',               'latitude' =>  8.9833, 'longitude' =>  7.4167],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Central Area',       'latitude' =>  9.0579, 'longitude' =>  7.4951],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Utako',              'latitude' =>  9.0667, 'longitude' =>  7.4667],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Jabi',               'latitude' =>  9.0667, 'longitude' =>  7.4333],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Kado',               'latitude' =>  9.0833, 'longitude' =>  7.4167],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Life Camp',          'latitude' =>  9.1000, 'longitude' =>  7.4000],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Katampe',            'latitude' =>  9.0833, 'longitude' =>  7.4500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Pyakasa',            'latitude' =>  8.9667, 'longitude' =>  7.3500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Idu Industrial',     'latitude' =>  9.0167, 'longitude' =>  7.3833],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Wumba',              'latitude' =>  8.9333, 'longitude' =>  7.4333],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Dawaki',             'latitude' =>  9.0833, 'longitude' =>  7.3833],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Gosa',               'latitude' =>  8.9833, 'longitude' =>  7.3500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Kpeyegyi',           'latitude' =>  8.9500, 'longitude' =>  7.3333],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Orozo',              'latitude' =>  9.0167, 'longitude' =>  7.5167],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Kurudu',             'latitude' =>  9.0333, 'longitude' =>  7.5000],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Mpape',              'latitude' =>  9.1000, 'longitude' =>  7.4667],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Kuchigoro',          'latitude' =>  9.1167, 'longitude' =>  7.4500],
            ['state' => 'FCT', 'lga' => 'Abuja Municipal', 'area' => 'Mabushi',            'latitude' =>  9.0667, 'longitude' =>  7.4167],

            // Bwari
            ['state' => 'FCT', 'lga' => 'Bwari', 'area' => 'Bwari',              'latitude' =>  9.0804, 'longitude' =>  7.3697],
            ['state' => 'FCT', 'lga' => 'Bwari', 'area' => 'Ushafa',             'latitude' =>  9.0500, 'longitude' =>  7.2833],
            ['state' => 'FCT', 'lga' => 'Bwari', 'area' => 'Dutse Alhaji',       'latitude' =>  9.0500, 'longitude' =>  7.3167],
            ['state' => 'FCT', 'lga' => 'Bwari', 'area' => 'Byazhin',            'latitude' =>  9.0667, 'longitude' =>  7.3000],
            ['state' => 'FCT', 'lga' => 'Bwari', 'area' => 'Garam',              'latitude' =>  9.1000, 'longitude' =>  7.3167],
            ['state' => 'FCT', 'lga' => 'Bwari', 'area' => 'Sabon Gari Bwari',  'latitude' =>  9.0667, 'longitude' =>  7.3667],

            // Gwagwalada
            ['state' => 'FCT', 'lga' => 'Gwagwalada', 'area' => 'Gwagwalada Township', 'latitude' => 8.9438, 'longitude' => 7.0806],
            ['state' => 'FCT', 'lga' => 'Gwagwalada', 'area' => 'Dobi',               'latitude' => 8.9167, 'longitude' => 7.0167],
            ['state' => 'FCT', 'lga' => 'Gwagwalada', 'area' => 'Zuba',               'latitude' => 9.0833, 'longitude' => 7.1833],
            ['state' => 'FCT', 'lga' => 'Gwagwalada', 'area' => 'Tunga',              'latitude' => 8.9333, 'longitude' => 7.0500],
            ['state' => 'FCT', 'lga' => 'Gwagwalada', 'area' => 'Yebu',               'latitude' => 8.9000, 'longitude' => 7.0500],

            // Kuje
            ['state' => 'FCT', 'lga' => 'Kuje', 'area' => 'Kuje Township',       'latitude' =>  8.8806, 'longitude' =>  7.2294],
            ['state' => 'FCT', 'lga' => 'Kuje', 'area' => 'Chibiri',             'latitude' =>  8.8667, 'longitude' =>  7.2500],
            ['state' => 'FCT', 'lga' => 'Kuje', 'area' => 'Rubochi',             'latitude' =>  8.8333, 'longitude' =>  7.2167],

            // Kwali
            ['state' => 'FCT', 'lga' => 'Kwali', 'area' => 'Kwali Township',     'latitude' =>  8.7428, 'longitude' =>  7.0153],
            ['state' => 'FCT', 'lga' => 'Kwali', 'area' => 'Yangoji',            'latitude' =>  8.7667, 'longitude' =>  7.0333],
            ['state' => 'FCT', 'lga' => 'Kwali', 'area' => 'Dafa',               'latitude' =>  8.7333, 'longitude' =>  7.0000],

            // Abaji
            ['state' => 'FCT', 'lga' => 'Abaji', 'area' => 'Abaji Township',     'latitude' =>  8.4719, 'longitude' =>  6.9419],
            ['state' => 'FCT', 'lga' => 'Abaji', 'area' => 'Yaba',               'latitude' =>  8.5000, 'longitude' =>  6.9667],
            ['state' => 'FCT', 'lga' => 'Abaji', 'area' => 'Rimba',              'latitude' =>  8.4500, 'longitude' =>  6.9167],

            // ================================================================
            // GOMBE STATE
            // ================================================================

            // Gombe
            ['state' => 'Gombe', 'lga' => 'Gombe', 'area' => 'Gombe Township',      'latitude' => 10.2897, 'longitude' => 11.1673],
            ['state' => 'Gombe', 'lga' => 'Gombe', 'area' => 'Tudun Wada',          'latitude' => 10.2833, 'longitude' => 11.1500],
            ['state' => 'Gombe', 'lga' => 'Gombe', 'area' => 'Pantami',             'latitude' => 10.3000, 'longitude' => 11.1833],
            ['state' => 'Gombe', 'lga' => 'Gombe', 'area' => 'Dawaki',              'latitude' => 10.2667, 'longitude' => 11.1500],
            ['state' => 'Gombe', 'lga' => 'Gombe', 'area' => 'Nasarawa',            'latitude' => 10.3167, 'longitude' => 11.2000],

            // ================================================================
            // IMO STATE
            // ================================================================

            // Owerri Municipal
            ['state' => 'Imo', 'lga' => 'Owerri Municipal', 'area' => 'Owerri Township',  'latitude' =>  5.4836, 'longitude' =>  7.0333],
            ['state' => 'Imo', 'lga' => 'Owerri Municipal', 'area' => 'GRA Owerri',       'latitude' =>  5.4833, 'longitude' =>  7.0167],
            ['state' => 'Imo', 'lga' => 'Owerri Municipal', 'area' => 'World Bank',       'latitude' =>  5.5000, 'longitude' =>  7.0333],
            ['state' => 'Imo', 'lga' => 'Owerri Municipal', 'area' => 'Trans Amadi',      'latitude' =>  5.4667, 'longitude' =>  7.0167],
            ['state' => 'Imo', 'lga' => 'Owerri Municipal', 'area' => 'New Owerri',       'latitude' =>  5.4833, 'longitude' =>  7.0500],

            // Owerri North
            ['state' => 'Imo', 'lga' => 'Owerri North', 'area' => 'Egbu',            'latitude' =>  5.5167, 'longitude' =>  7.0333],
            ['state' => 'Imo', 'lga' => 'Owerri North', 'area' => 'Azaraegbelu',     'latitude' =>  5.5333, 'longitude' =>  7.0167],

            // Owerri West
            ['state' => 'Imo', 'lga' => 'Owerri West', 'area' => 'Orji',             'latitude' =>  5.5000, 'longitude' =>  7.0000],
            ['state' => 'Imo', 'lga' => 'Owerri West', 'area' => 'Nekede',           'latitude' =>  5.4667, 'longitude' =>  6.9833],
            ['state' => 'Imo', 'lga' => 'Owerri West', 'area' => 'Ihiagwa',          'latitude' =>  5.4833, 'longitude' =>  6.9667],

            // Okigwe
            ['state' => 'Imo', 'lga' => 'Okigwe', 'area' => 'Okigwe Township',      'latitude' =>  5.8500, 'longitude' =>  7.3500],
            ['state' => 'Imo', 'lga' => 'Okigwe', 'area' => 'Umuihi',               'latitude' =>  5.8667, 'longitude' =>  7.3667],

            // Orlu
            ['state' => 'Imo', 'lga' => 'Orlu', 'area' => 'Orlu Township',          'latitude' =>  5.7833, 'longitude' =>  7.0333],
            ['state' => 'Imo', 'lga' => 'Orlu', 'area' => 'Amaifeke',               'latitude' =>  5.7667, 'longitude' =>  7.0167],

            // ================================================================
            // JIGAWA STATE
            // ================================================================

            // Dutse
            ['state' => 'Jigawa', 'lga' => 'Dutse', 'area' => 'Dutse Township',     'latitude' => 11.7667, 'longitude' =>  9.3500],
            ['state' => 'Jigawa', 'lga' => 'Dutse', 'area' => 'Sabon Gari Dutse',   'latitude' => 11.7833, 'longitude' =>  9.3333],

            // Hadejia
            ['state' => 'Jigawa', 'lga' => 'Hadejia', 'area' => 'Hadejia Township', 'latitude' => 12.4500, 'longitude' => 10.0500],
            ['state' => 'Jigawa', 'lga' => 'Hadejia', 'area' => 'Sabon Gari Hadejia','latitude' => 12.4667, 'longitude' => 10.0333],

            // ================================================================
            // KADUNA STATE
            // ================================================================

            // Kaduna North
            ['state' => 'Kaduna', 'lga' => 'Kaduna North', 'area' => 'Kaduna City Centre', 'latitude' => 10.5333, 'longitude' => 7.4333],
            ['state' => 'Kaduna', 'lga' => 'Kaduna North', 'area' => 'Rigasa',             'latitude' => 10.5667, 'longitude' => 7.3833],
            ['state' => 'Kaduna', 'lga' => 'Kaduna North', 'area' => 'Badarawa',           'latitude' => 10.5500, 'longitude' => 7.4333],
            ['state' => 'Kaduna', 'lga' => 'Kaduna North', 'area' => 'Kawo',               'latitude' => 10.5833, 'longitude' => 7.4500],
            ['state' => 'Kaduna', 'lga' => 'Kaduna North', 'area' => 'Tudun Wada',         'latitude' => 10.5500, 'longitude' => 7.4167],
            ['state' => 'Kaduna', 'lga' => 'Kaduna North', 'area' => 'Nasarawa',           'latitude' => 10.5167, 'longitude' => 7.4167],

            // Kaduna South
            ['state' => 'Kaduna', 'lga' => 'Kaduna South', 'area' => 'Barnawa',        'latitude' => 10.4667, 'longitude' => 7.4500],
            ['state' => 'Kaduna', 'lga' => 'Kaduna South', 'area' => 'Kakuri',         'latitude' => 10.4500, 'longitude' => 7.4667],
            ['state' => 'Kaduna', 'lga' => 'Kaduna South', 'area' => 'Gonin Gora',     'latitude' => 10.4333, 'longitude' => 7.4167],
            ['state' => 'Kaduna', 'lga' => 'Kaduna South', 'area' => 'Makera',         'latitude' => 10.4833, 'longitude' => 7.4667],
            ['state' => 'Kaduna', 'lga' => 'Kaduna South', 'area' => 'Television',     'latitude' => 10.4833, 'longitude' => 7.4333],

            // Zaria
            ['state' => 'Kaduna', 'lga' => 'Zaria', 'area' => 'Zaria City',          'latitude' => 11.0833, 'longitude' =>  7.7000],
            ['state' => 'Kaduna', 'lga' => 'Zaria', 'area' => 'Sabon Gari Zaria',    'latitude' => 11.1000, 'longitude' =>  7.7167],
            ['state' => 'Kaduna', 'lga' => 'Zaria', 'area' => 'Tudun Wada Zaria',    'latitude' => 11.0833, 'longitude' =>  7.7167],
            ['state' => 'Kaduna', 'lga' => 'Zaria', 'area' => 'Samaru',              'latitude' => 11.1667, 'longitude' =>  7.6500],
            ['state' => 'Kaduna', 'lga' => 'Zaria', 'area' => 'Kwangila',            'latitude' => 11.0500, 'longitude' =>  7.7333],

            // ================================================================
            // KANO STATE
            // ================================================================

            // Kano Municipal
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Fagge',        'latitude' => 12.0197, 'longitude' =>  8.5361],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Gwale',        'latitude' => 11.9932, 'longitude' =>  8.5229],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Dala',         'latitude' => 12.0622, 'longitude' =>  8.5508],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Tarauni',      'latitude' => 12.0411, 'longitude' =>  8.5636],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Nassarawa',    'latitude' => 11.9804, 'longitude' =>  8.5650],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Kofar Ruwa',   'latitude' => 12.0000, 'longitude' =>  8.5833],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Kurmi Market', 'latitude' => 12.0000, 'longitude' =>  8.5667],
            ['state' => 'Kano', 'lga' => 'Kano Municipal', 'area' => 'Sabon Gari',   'latitude' => 12.0167, 'longitude' =>  8.5333],

            // Ungogo
            ['state' => 'Kano', 'lga' => 'Ungogo', 'area' => 'Ungogo',              'latitude' => 12.1000, 'longitude' =>  8.5333],
            ['state' => 'Kano', 'lga' => 'Ungogo', 'area' => 'Dorayi',              'latitude' => 12.0667, 'longitude' =>  8.5000],
            ['state' => 'Kano', 'lga' => 'Ungogo', 'area' => 'Sharada',             'latitude' => 12.0333, 'longitude' =>  8.5167],

            // Kumbotso
            ['state' => 'Kano', 'lga' => 'Kumbotso', 'area' => 'Kumbotso',         'latitude' => 12.0833, 'longitude' =>  8.4833],
            ['state' => 'Kano', 'lga' => 'Kumbotso', 'area' => 'Sanni Mainagge',   'latitude' => 12.0667, 'longitude' =>  8.4667],
            ['state' => 'Kano', 'lga' => 'Kumbotso', 'area' => 'Kofar Nassarawa',  'latitude' => 12.0500, 'longitude' =>  8.5000],

            // ================================================================
            // KATSINA STATE
            // ================================================================

            // Katsina
            ['state' => 'Katsina', 'lga' => 'Katsina', 'area' => 'Katsina City',    'latitude' => 12.9889, 'longitude' =>  7.6006],
            ['state' => 'Katsina', 'lga' => 'Katsina', 'area' => 'Sabon Gari Katsina', 'latitude' => 13.0000, 'longitude' => 7.5833],
            ['state' => 'Katsina', 'lga' => 'Katsina', 'area' => 'Kofar Kaura',     'latitude' => 12.9833, 'longitude' =>  7.6000],

            // Funtua
            ['state' => 'Katsina', 'lga' => 'Funtua', 'area' => 'Funtua Township',  'latitude' => 11.5167, 'longitude' =>  7.3167],
            ['state' => 'Katsina', 'lga' => 'Funtua', 'area' => 'Sabon Gari Funtua','latitude' => 11.5333, 'longitude' =>  7.3000],

            // ================================================================
            // KEBBI STATE
            // ================================================================

            // Birnin Kebbi
            ['state' => 'Kebbi', 'lga' => 'Birnin Kebbi', 'area' => 'Birnin Kebbi Township', 'latitude' => 12.4500, 'longitude' => 4.1833],
            ['state' => 'Kebbi', 'lga' => 'Birnin Kebbi', 'area' => 'Kalgo Road',             'latitude' => 12.4667, 'longitude' => 4.2000],
            ['state' => 'Kebbi', 'lga' => 'Birnin Kebbi', 'area' => 'Sabon Gari Birnin Kebbi','latitude' => 12.4333, 'longitude' => 4.1667],

            // ================================================================
            // KOGI STATE
            // ================================================================

            // Lokoja
            ['state' => 'Kogi', 'lga' => 'Lokoja', 'area' => 'Lokoja Township',     'latitude' =>  7.8036, 'longitude' =>  6.7330],
            ['state' => 'Kogi', 'lga' => 'Lokoja', 'area' => 'Felele',              'latitude' =>  7.8167, 'longitude' =>  6.7500],
            ['state' => 'Kogi', 'lga' => 'Lokoja', 'area' => 'GRA Lokoja',          'latitude' =>  7.7833, 'longitude' =>  6.7333],
            ['state' => 'Kogi', 'lga' => 'Lokoja', 'area' => 'Adankolo',            'latitude' =>  7.8333, 'longitude' =>  6.7167],
            ['state' => 'Kogi', 'lga' => 'Lokoja', 'area' => 'Ganaja',              'latitude' =>  7.8500, 'longitude' =>  6.7500],

            // Okene
            ['state' => 'Kogi', 'lga' => 'Okene', 'area' => 'Okene Township',       'latitude' =>  7.5500, 'longitude' =>  6.2333],
            ['state' => 'Kogi', 'lga' => 'Okene', 'area' => 'Eba',                  'latitude' =>  7.5333, 'longitude' =>  6.2167],

            // ================================================================
            // KWARA STATE
            // ================================================================

            // Ilorin West
            ['state' => 'Kwara', 'lga' => 'Ilorin West', 'area' => 'Ilorin Township',  'latitude' =>  8.4966, 'longitude' =>  4.5421],
            ['state' => 'Kwara', 'lga' => 'Ilorin West', 'area' => 'GRA Ilorin',       'latitude' =>  8.4833, 'longitude' =>  4.5500],
            ['state' => 'Kwara', 'lga' => 'Ilorin West', 'area' => 'Kulende',          'latitude' =>  8.5000, 'longitude' =>  4.5167],
            ['state' => 'Kwara', 'lga' => 'Ilorin West', 'area' => 'Mandate',          'latitude' =>  8.5167, 'longitude' =>  4.5333],
            ['state' => 'Kwara', 'lga' => 'Ilorin West', 'area' => 'Oke Ogun',         'latitude' =>  8.5167, 'longitude' =>  4.5500],

            // Ilorin East
            ['state' => 'Kwara', 'lga' => 'Ilorin East', 'area' => 'Tanke',           'latitude' =>  8.5000, 'longitude' =>  4.6167],
            ['state' => 'Kwara', 'lga' => 'Ilorin East', 'area' => 'Fate',            'latitude' =>  8.4833, 'longitude' =>  4.6500],
            ['state' => 'Kwara', 'lga' => 'Ilorin East', 'area' => 'Amilegbe',        'latitude' =>  8.5167, 'longitude' =>  4.6000],

            // Ilorin South
            ['state' => 'Kwara', 'lga' => 'Ilorin South', 'area' => 'Oke-Ose',       'latitude' =>  8.4667, 'longitude' =>  4.5333],
            ['state' => 'Kwara', 'lga' => 'Ilorin South', 'area' => 'Maraba',        'latitude' =>  8.4500, 'longitude' =>  4.5500],
            ['state' => 'Kwara', 'lga' => 'Ilorin South', 'area' => 'Agbeyangi',     'latitude' =>  8.4333, 'longitude' =>  4.5667],

            // ================================================================
            // LAGOS STATE
            // ================================================================

            // Alimosho
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Egbeda',           'latitude' =>  6.6167, 'longitude' =>  3.2833],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Ipaja',            'latitude' =>  6.6167, 'longitude' =>  3.2500],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Dopemu',           'latitude' =>  6.6000, 'longitude' =>  3.3167],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Idimu',            'latitude' =>  6.6000, 'longitude' =>  3.2667],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Iyana Ipaja',      'latitude' =>  6.6167, 'longitude' =>  3.2833],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Meiran',           'latitude' =>  6.6500, 'longitude' =>  3.2833],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Akowonjo',         'latitude' =>  6.6167, 'longitude' =>  3.3167],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Shasha',           'latitude' =>  6.6000, 'longitude' =>  3.3000],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Abule Egba',       'latitude' =>  6.6333, 'longitude' =>  3.2667],
            ['state' => 'Lagos', 'lga' => 'Alimosho', 'area' => 'Pleasure',         'latitude' =>  6.5833, 'longitude' =>  3.2833],

            // Ikeja
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Ikeja',               'latitude' =>  6.5954, 'longitude' =>  3.3378],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Allen Avenue',        'latitude' =>  6.6167, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Alausa',              'latitude' =>  6.5833, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Oregun',              'latitude' =>  6.6000, 'longitude' =>  3.3667],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Opebi',               'latitude' =>  6.6167, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Maryland',            'latitude' =>  6.5667, 'longitude' =>  3.3667],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'Ojota',               'latitude' =>  6.5833, 'longitude' =>  3.3833],
            ['state' => 'Lagos', 'lga' => 'Ikeja', 'area' => 'GRA Ikeja',           'latitude' =>  6.5833, 'longitude' =>  3.3333],

            // Surulere
            ['state' => 'Lagos', 'lga' => 'Surulere', 'area' => 'Surulere',         'latitude' =>  6.5059, 'longitude' =>  3.3565],
            ['state' => 'Lagos', 'lga' => 'Surulere', 'area' => 'Aguda',            'latitude' =>  6.5000, 'longitude' =>  3.3667],
            ['state' => 'Lagos', 'lga' => 'Surulere', 'area' => 'Itire',            'latitude' =>  6.5000, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Surulere', 'area' => 'Ojuelegba',        'latitude' =>  6.5167, 'longitude' =>  3.3667],
            ['state' => 'Lagos', 'lga' => 'Surulere', 'area' => 'Bode Thomas',      'latitude' =>  6.5000, 'longitude' =>  3.3500],

            // Eti-Osa
            ['state' => 'Lagos', 'lga' => 'Eti-Osa', 'area' => 'Lekki Phase 1',    'latitude' =>  6.4333, 'longitude' =>  3.4833],
            ['state' => 'Lagos', 'lga' => 'Eti-Osa', 'area' => 'Victoria Island',  'latitude' =>  6.4286, 'longitude' =>  3.4218],
            ['state' => 'Lagos', 'lga' => 'Eti-Osa', 'area' => 'Ikoyi',            'latitude' =>  6.4500, 'longitude' =>  3.4333],
            ['state' => 'Lagos', 'lga' => 'Eti-Osa', 'area' => 'Ajah',             'latitude' =>  6.4667, 'longitude' =>  3.5667],
            ['state' => 'Lagos', 'lga' => 'Eti-Osa', 'area' => 'Sangotedo',        'latitude' =>  6.4500, 'longitude' =>  3.6167],
            ['state' => 'Lagos', 'lga' => 'Eti-Osa', 'area' => 'Chevron',          'latitude' =>  6.4333, 'longitude' =>  3.5333],

            // Kosofe
            ['state' => 'Lagos', 'lga' => 'Kosofe', 'area' => 'Ketu',              'latitude' =>  6.5833, 'longitude' =>  3.3833],
            ['state' => 'Lagos', 'lga' => 'Kosofe', 'area' => 'Ojota',             'latitude' =>  6.5833, 'longitude' =>  3.3833],
            ['state' => 'Lagos', 'lga' => 'Kosofe', 'area' => 'Ogudu',             'latitude' =>  6.5667, 'longitude' =>  3.4000],
            ['state' => 'Lagos', 'lga' => 'Kosofe', 'area' => 'Agboville',         'latitude' =>  6.5667, 'longitude' =>  3.4167],
            ['state' => 'Lagos', 'lga' => 'Kosofe', 'area' => 'Alapere',           'latitude' =>  6.5833, 'longitude' =>  3.4000],

            // Ikorodu
            ['state' => 'Lagos', 'lga' => 'Ikorodu', 'area' => 'Ikorodu',          'latitude' =>  6.6194, 'longitude' =>  3.5064],
            ['state' => 'Lagos', 'lga' => 'Ikorodu', 'area' => 'Imota',            'latitude' =>  6.6500, 'longitude' =>  3.6167],
            ['state' => 'Lagos', 'lga' => 'Ikorodu', 'area' => 'Ijede',            'latitude' =>  6.5833, 'longitude' =>  3.6000],
            ['state' => 'Lagos', 'lga' => 'Ikorodu', 'area' => 'Igbogbo',          'latitude' =>  6.5833, 'longitude' =>  3.5167],
            ['state' => 'Lagos', 'lga' => 'Ikorodu', 'area' => 'Agric',            'latitude' =>  6.6167, 'longitude' =>  3.4833],

            // Lagos Island
            ['state' => 'Lagos', 'lga' => 'Lagos Island', 'area' => 'Lagos Island', 'latitude' => 6.4543, 'longitude' => 3.3940],
            ['state' => 'Lagos', 'lga' => 'Lagos Island', 'area' => 'Lagos Marina',  'latitude' => 6.4500, 'longitude' => 3.4000],
            ['state' => 'Lagos', 'lga' => 'Lagos Island', 'area' => 'Broad Street',  'latitude' => 6.4500, 'longitude' => 3.3833],
            ['state' => 'Lagos', 'lga' => 'Lagos Island', 'area' => 'Idumota',       'latitude' => 6.4500, 'longitude' => 3.3833],

            // Lagos Mainland
            ['state' => 'Lagos', 'lga' => 'Lagos Mainland', 'area' => 'Ebute Metta',  'latitude' => 6.4833, 'longitude' => 3.3833],
            ['state' => 'Lagos', 'lga' => 'Lagos Mainland', 'area' => 'Yaba',          'latitude' => 6.5000, 'longitude' => 3.3833],
            ['state' => 'Lagos', 'lga' => 'Lagos Mainland', 'area' => 'Oyingbo',       'latitude' => 6.4833, 'longitude' => 3.3833],
            ['state' => 'Lagos', 'lga' => 'Lagos Mainland', 'area' => 'Sabo',          'latitude' => 6.5000, 'longitude' => 3.3833],

            // Mushin
            ['state' => 'Lagos', 'lga' => 'Mushin', 'area' => 'Mushin',            'latitude' =>  6.5298, 'longitude' =>  3.3544],
            ['state' => 'Lagos', 'lga' => 'Mushin', 'area' => 'Idi Araba',         'latitude' =>  6.5167, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Mushin', 'area' => 'Isale Eko',         'latitude' =>  6.5333, 'longitude' =>  3.3667],

            // Oshodi-Isolo
            ['state' => 'Lagos', 'lga' => 'Oshodi-Isolo', 'area' => 'Oshodi',       'latitude' =>  6.5477, 'longitude' =>  3.3392],
            ['state' => 'Lagos', 'lga' => 'Oshodi-Isolo', 'area' => 'Isolo',        'latitude' =>  6.5333, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Oshodi-Isolo', 'area' => 'Mafoluku',     'latitude' =>  6.5500, 'longitude' =>  3.3333],
            ['state' => 'Lagos', 'lga' => 'Oshodi-Isolo', 'area' => 'Cele',         'latitude' =>  6.5333, 'longitude' =>  3.3333],

            // Agege
            ['state' => 'Lagos', 'lga' => 'Agege', 'area' => 'Agege',              'latitude' =>  6.6208, 'longitude' =>  3.3228],
            ['state' => 'Lagos', 'lga' => 'Agege', 'area' => 'Ifako',              'latitude' =>  6.6167, 'longitude' =>  3.3000],
            ['state' => 'Lagos', 'lga' => 'Agege', 'area' => 'Oko-Oba',            'latitude' =>  6.6333, 'longitude' =>  3.3000],
            ['state' => 'Lagos', 'lga' => 'Agege', 'area' => 'Pen Cinema',         'latitude' =>  6.6167, 'longitude' =>  3.3333],

            // Amuwo-Odofin
            ['state' => 'Lagos', 'lga' => 'Amuwo-Odofin', 'area' => 'Satellite Town', 'latitude' => 6.4833, 'longitude' => 3.3000],
            ['state' => 'Lagos', 'lga' => 'Amuwo-Odofin', 'area' => 'Mile 2',          'latitude' => 6.4833, 'longitude' => 3.3167],
            ['state' => 'Lagos', 'lga' => 'Amuwo-Odofin', 'area' => 'Festac Town',     'latitude' => 6.4667, 'longitude' => 3.2833],
            ['state' => 'Lagos', 'lga' => 'Amuwo-Odofin', 'area' => 'Alakija',         'latitude' => 6.4667, 'longitude' => 3.2833],

            // Apapa
            ['state' => 'Lagos', 'lga' => 'Apapa', 'area' => 'Apapa',              'latitude' =>  6.4490, 'longitude' =>  3.3600],
            ['state' => 'Lagos', 'lga' => 'Apapa', 'area' => 'Ajegunle',           'latitude' =>  6.4667, 'longitude' =>  3.3500],
            ['state' => 'Lagos', 'lga' => 'Apapa', 'area' => 'Wharf',              'latitude' =>  6.4500, 'longitude' =>  3.3667],

            // Shomolu
            ['state' => 'Lagos', 'lga' => 'Shomolu', 'area' => 'Shomolu',          'latitude' =>  6.5455, 'longitude' =>  3.3797],
            ['state' => 'Lagos', 'lga' => 'Shomolu', 'area' => 'Bariga',           'latitude' =>  6.5333, 'longitude' =>  3.3833],
            ['state' => 'Lagos', 'lga' => 'Shomolu', 'area' => 'Gbagada',          'latitude' =>  6.5500, 'longitude' =>  3.3833],

            // Ojo
            ['state' => 'Lagos', 'lga' => 'Ojo', 'area' => 'Ojo',                  'latitude' =>  6.4667, 'longitude' =>  3.2167],
            ['state' => 'Lagos', 'lga' => 'Ojo', 'area' => 'Iba',                  'latitude' =>  6.4667, 'longitude' =>  3.1833],
            ['state' => 'Lagos', 'lga' => 'Ojo', 'area' => 'Ijanikin',             'latitude' =>  6.4500, 'longitude' =>  3.1667],
            ['state' => 'Lagos', 'lga' => 'Ojo', 'area' => 'Trade Fair',           'latitude' =>  6.4833, 'longitude' =>  3.2667],

            // Ifako-Ijaiye
            ['state' => 'Lagos', 'lga' => 'Ifako-Ijaiye', 'area' => 'Ifako',       'latitude' =>  6.6500, 'longitude' =>  3.2833],
            ['state' => 'Lagos', 'lga' => 'Ifako-Ijaiye', 'area' => 'Ijaiye',      'latitude' =>  6.6333, 'longitude' =>  3.2833],
            ['state' => 'Lagos', 'lga' => 'Ifako-Ijaiye', 'area' => 'Ogba',        'latitude' =>  6.6167, 'longitude' =>  3.3167],

            // Badagry
            ['state' => 'Lagos', 'lga' => 'Badagry', 'area' => 'Badagry Township', 'latitude' =>  6.4143, 'longitude' =>  2.8879],
            ['state' => 'Lagos', 'lga' => 'Badagry', 'area' => 'Seme Border',      'latitude' =>  6.3500, 'longitude' =>  2.7167],
            ['state' => 'Lagos', 'lga' => 'Badagry', 'area' => 'Agbara',           'latitude' =>  6.5167, 'longitude' =>  3.0833],

            // ================================================================
            // NASARAWA STATE
            // ================================================================

            // Lafia
            ['state' => 'Nasarawa', 'lga' => 'Lafia', 'area' => 'Lafia Township',  'latitude' =>  8.4860, 'longitude' =>  8.5210],
            ['state' => 'Nasarawa', 'lga' => 'Lafia', 'area' => 'New Market',      'latitude' =>  8.4833, 'longitude' =>  8.5333],
            ['state' => 'Nasarawa', 'lga' => 'Lafia', 'area' => 'Shabu',           'latitude' =>  8.5000, 'longitude' =>  8.5167],
            ['state' => 'Nasarawa', 'lga' => 'Lafia', 'area' => 'Makurdi Road',    'latitude' =>  8.4667, 'longitude' =>  8.5333],

            // Karu
            ['state' => 'Nasarawa', 'lga' => 'Karu', 'area' => 'Karu',             'latitude' =>  8.7167, 'longitude' =>  7.4500],
            ['state' => 'Nasarawa', 'lga' => 'Karu', 'area' => 'Mararaba',         'latitude' =>  8.7500, 'longitude' =>  7.5000],
            ['state' => 'Nasarawa', 'lga' => 'Karu', 'area' => 'New Nyanya',       'latitude' =>  8.7833, 'longitude' =>  7.4833],
            ['state' => 'Nasarawa', 'lga' => 'Karu', 'area' => 'Ado',              'latitude' =>  8.7333, 'longitude' =>  7.4667],
            ['state' => 'Nasarawa', 'lga' => 'Karu', 'area' => 'Masaka',           'latitude' =>  8.6833, 'longitude' =>  7.5500],
            ['state' => 'Nasarawa', 'lga' => 'Karu', 'area' => 'Gitata',           'latitude' =>  8.7000, 'longitude' =>  7.5167],

            // Keffi
            ['state' => 'Nasarawa', 'lga' => 'Keffi', 'area' => 'Keffi Township',  'latitude' =>  8.8500, 'longitude' =>  7.8667],
            ['state' => 'Nasarawa', 'lga' => 'Keffi', 'area' => 'Sabon Gari Keffi','latitude' =>  8.8667, 'longitude' =>  7.8500],

            // ================================================================
            // NIGER STATE
            // ================================================================

            // Chanchaga
            ['state' => 'Niger', 'lga' => 'Chanchaga', 'area' => 'Minna',           'latitude' =>  9.6167, 'longitude' =>  6.5500],
            ['state' => 'Niger', 'lga' => 'Chanchaga', 'area' => 'Tunga',           'latitude' =>  9.6333, 'longitude' =>  6.5333],
            ['state' => 'Niger', 'lga' => 'Chanchaga', 'area' => 'Bosso',           'latitude' =>  9.6500, 'longitude' =>  6.5500],

            // Bosso
            ['state' => 'Niger', 'lga' => 'Bosso', 'area' => 'Bosso Township',     'latitude' =>  9.6833, 'longitude' =>  6.5333],
            ['state' => 'Niger', 'lga' => 'Bosso', 'area' => 'Kpakungu',           'latitude' =>  9.6500, 'longitude' =>  6.5667],
            ['state' => 'Niger', 'lga' => 'Bosso', 'area' => 'Kwangila',           'latitude' =>  9.7000, 'longitude' =>  6.5167],

            // Suleja
            ['state' => 'Niger', 'lga' => 'Suleja', 'area' => 'Suleja Township',   'latitude' =>  9.1833, 'longitude' =>  7.1833],
            ['state' => 'Niger', 'lga' => 'Suleja', 'area' => 'Maje',              'latitude' =>  9.1667, 'longitude' =>  7.2000],
            ['state' => 'Niger', 'lga' => 'Suleja', 'area' => 'Sabon Wuse',        'latitude' =>  9.2000, 'longitude' =>  7.1667],

            // ================================================================
            // OGUN STATE
            // ================================================================

            // Abeokuta South
            ['state' => 'Ogun', 'lga' => 'Abeokuta South', 'area' => 'Abeokuta',    'latitude' =>  7.1456, 'longitude' =>  3.3522],
            ['state' => 'Ogun', 'lga' => 'Abeokuta South', 'area' => 'Sapon',       'latitude' =>  7.1333, 'longitude' =>  3.3333],
            ['state' => 'Ogun', 'lga' => 'Abeokuta South', 'area' => 'Ijaiye',      'latitude' =>  7.1500, 'longitude' =>  3.3333],
            ['state' => 'Ogun', 'lga' => 'Abeokuta South', 'area' => 'Oke Lantoro', 'latitude' =>  7.1667, 'longitude' =>  3.3500],

            // Abeokuta North
            ['state' => 'Ogun', 'lga' => 'Abeokuta North', 'area' => 'Lafenwa',    'latitude' =>  7.1833, 'longitude' =>  3.3333],
            ['state' => 'Ogun', 'lga' => 'Abeokuta North', 'area' => 'Kemta',      'latitude' =>  7.2000, 'longitude' =>  3.3333],
            ['state' => 'Ogun', 'lga' => 'Abeokuta North', 'area' => 'Ijaye',      'latitude' =>  7.2000, 'longitude' =>  3.3667],

            // Sagamu
            ['state' => 'Ogun', 'lga' => 'Sagamu', 'area' => 'Sagamu Township',    'latitude' =>  6.8404, 'longitude' =>  3.6374],
            ['state' => 'Ogun', 'lga' => 'Sagamu', 'area' => 'Makun',              'latitude' =>  6.8333, 'longitude' =>  3.6333],

            // Ado-Odo/Ota
            ['state' => 'Ogun', 'lga' => 'Ado-Odo/Ota', 'area' => 'Ota',          'latitude' =>  6.6833, 'longitude' =>  3.2333],
            ['state' => 'Ogun', 'lga' => 'Ado-Odo/Ota', 'area' => 'Sango Ota',    'latitude' =>  6.7500, 'longitude' =>  3.1500],
            ['state' => 'Ogun', 'lga' => 'Ado-Odo/Ota', 'area' => 'Idiroko',      'latitude' =>  6.7667, 'longitude' =>  2.9667],

            // ================================================================
            // ONDO STATE
            // ================================================================

            // Akure South
            ['state' => 'Ondo', 'lga' => 'Akure South', 'area' => 'Akure',          'latitude' =>  7.2526, 'longitude' =>  5.1930],
            ['state' => 'Ondo', 'lga' => 'Akure South', 'area' => 'Shagari Village','latitude' =>  7.2333, 'longitude' =>  5.1833],
            ['state' => 'Ondo', 'lga' => 'Akure South', 'area' => 'Oda Road',       'latitude' =>  7.2667, 'longitude' =>  5.1833],
            ['state' => 'Ondo', 'lga' => 'Akure South', 'area' => 'FUTA Area',      'latitude' =>  7.3000, 'longitude' =>  5.1333],

            // Owo
            ['state' => 'Ondo', 'lga' => 'Owo', 'area' => 'Owo Township',          'latitude' =>  7.1978, 'longitude' =>  5.5889],
            ['state' => 'Ondo', 'lga' => 'Owo', 'area' => 'Oke Abuku',             'latitude' =>  7.2000, 'longitude' =>  5.5667],

            // ================================================================
            // OSUN STATE
            // ================================================================

            // Osogbo
            ['state' => 'Osun', 'lga' => 'Osogbo', 'area' => 'Osogbo Township',    'latitude' =>  7.7667, 'longitude' =>  4.5500],
            ['state' => 'Osun', 'lga' => 'Osogbo', 'area' => 'Station Road',       'latitude' =>  7.7500, 'longitude' =>  4.5333],
            ['state' => 'Osun', 'lga' => 'Osogbo', 'area' => 'Old Garage',         'latitude' =>  7.7833, 'longitude' =>  4.5500],
            ['state' => 'Osun', 'lga' => 'Osogbo', 'area' => 'GRA Osogbo',         'latitude' =>  7.7833, 'longitude' =>  4.5667],

            // Ife Central
            ['state' => 'Osun', 'lga' => 'Ife Central', 'area' => 'Ile-Ife',       'latitude' =>  7.4833, 'longitude' =>  4.5500],
            ['state' => 'Osun', 'lga' => 'Ife Central', 'area' => 'OAU Campus',    'latitude' =>  7.5167, 'longitude' =>  4.5167],
            ['state' => 'Osun', 'lga' => 'Ife Central', 'area' => 'Moore',         'latitude' =>  7.4833, 'longitude' =>  4.5333],

            // Ilesa West
            ['state' => 'Osun', 'lga' => 'Ilesa West', 'area' => 'Ilesa',          'latitude' =>  7.6167, 'longitude' =>  4.7333],
            ['state' => 'Osun', 'lga' => 'Ilesa West', 'area' => 'Ilesa East',     'latitude' =>  7.6333, 'longitude' =>  4.7500],

            // ================================================================
            // OYO STATE
            // ================================================================

            // Ibadan North
            ['state' => 'Oyo', 'lga' => 'Ibadan North', 'area' => 'UI Area',       'latitude' =>  7.4000, 'longitude' =>  3.9000],
            ['state' => 'Oyo', 'lga' => 'Ibadan North', 'area' => 'Bodija',        'latitude' =>  7.4167, 'longitude' =>  3.9000],
            ['state' => 'Oyo', 'lga' => 'Ibadan North', 'area' => 'Sango',         'latitude' =>  7.4333, 'longitude' =>  3.8833],
            ['state' => 'Oyo', 'lga' => 'Ibadan North', 'area' => 'Agodi',         'latitude' =>  7.4000, 'longitude' =>  3.9000],
            ['state' => 'Oyo', 'lga' => 'Ibadan North', 'area' => 'Mokola',        'latitude' =>  7.4000, 'longitude' =>  3.8833],

            // Ibadan North West
            ['state' => 'Oyo', 'lga' => 'Ibadan North West', 'area' => 'Oke-Are',  'latitude' =>  7.4167, 'longitude' =>  3.8833],
            ['state' => 'Oyo', 'lga' => 'Ibadan North West', 'area' => 'Eleyele',  'latitude' =>  7.4333, 'longitude' =>  3.8667],
            ['state' => 'Oyo', 'lga' => 'Ibadan North West', 'area' => 'Ashi',     'latitude' =>  7.4167, 'longitude' =>  3.8667],

            // Ibadan North East
            ['state' => 'Oyo', 'lga' => 'Ibadan North East', 'area' => 'Iwo Road', 'latitude' =>  7.3833, 'longitude' =>  3.9167],
            ['state' => 'Oyo', 'lga' => 'Ibadan North East', 'area' => 'Ojoo',     'latitude' =>  7.4333, 'longitude' =>  3.9333],
            ['state' => 'Oyo', 'lga' => 'Ibadan North East', 'area' => 'Bashorun',  'latitude' =>  7.4167, 'longitude' =>  3.9333],

            // Ibadan South West
            ['state' => 'Oyo', 'lga' => 'Ibadan South West', 'area' => 'Ring Road', 'latitude' => 7.3833, 'longitude' => 3.8833],
            ['state' => 'Oyo', 'lga' => 'Ibadan South West', 'area' => 'Dugbe',     'latitude' => 7.3833, 'longitude' => 3.8833],
            ['state' => 'Oyo', 'lga' => 'Ibadan South West', 'area' => 'Isale Ijebu','latitude' => 7.3667, 'longitude' => 3.8833],

            // Ibadan South East
            ['state' => 'Oyo', 'lga' => 'Ibadan South East', 'area' => 'Oje',       'latitude' => 7.3667, 'longitude' => 3.9167],
            ['state' => 'Oyo', 'lga' => 'Ibadan South East', 'area' => 'Beere',     'latitude' => 7.3833, 'longitude' => 3.9000],
            ['state' => 'Oyo', 'lga' => 'Ibadan South East', 'area' => 'Mapo',      'latitude' => 7.3833, 'longitude' => 3.9000],

            // Egbeda
            ['state' => 'Oyo', 'lga' => 'Egbeda', 'area' => 'Egbeda',              'latitude' =>  7.4297, 'longitude' =>  3.8906],
            ['state' => 'Oyo', 'lga' => 'Egbeda', 'area' => 'Akobo',               'latitude' =>  7.4167, 'longitude' =>  3.8833],
            ['state' => 'Oyo', 'lga' => 'Egbeda', 'area' => 'Oluyole Estate',      'latitude' =>  7.4000, 'longitude' =>  3.8667],

            // Ogbomoso North
            ['state' => 'Oyo', 'lga' => 'Ogbomoso North', 'area' => 'Ogbomoso',    'latitude' =>  8.1500, 'longitude' =>  4.2500],
            ['state' => 'Oyo', 'lga' => 'Ogbomoso North', 'area' => 'Arowomole',   'latitude' =>  8.1333, 'longitude' =>  4.2333],
            ['state' => 'Oyo', 'lga' => 'Ogbomoso North', 'area' => 'Oke Ogun',    'latitude' =>  8.1667, 'longitude' =>  4.2167],

            // ================================================================
            // PLATEAU STATE
            // ================================================================

            // Jos North
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Jos Township',  'latitude' =>  9.9167, 'longitude' =>  8.8833],
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Laranto',       'latitude' =>  9.9333, 'longitude' =>  8.8667],
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Tudun Wada',    'latitude' =>  9.9000, 'longitude' =>  8.9000],
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Angwan Rogo',   'latitude' =>  9.9500, 'longitude' =>  8.8833],
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Nassarawa',     'latitude' =>  9.9000, 'longitude' =>  8.8667],
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Dogon Dutse',   'latitude' =>  9.9500, 'longitude' =>  8.9000],
            ['state' => 'Plateau', 'lga' => 'Jos North', 'area' => 'Tafawa Balewa', 'latitude' =>  9.9333, 'longitude' =>  8.9167],

            // Jos South
            ['state' => 'Plateau', 'lga' => 'Jos South', 'area' => 'Bukuru',        'latitude' =>  9.7833, 'longitude' =>  8.8667],
            ['state' => 'Plateau', 'lga' => 'Jos South', 'area' => 'Rayfield',      'latitude' =>  9.8167, 'longitude' =>  8.8833],
            ['state' => 'Plateau', 'lga' => 'Jos South', 'area' => 'Shen',          'latitude' =>  9.8500, 'longitude' =>  8.9000],
            ['state' => 'Plateau', 'lga' => 'Jos South', 'area' => 'Gyel',          'latitude' =>  9.8000, 'longitude' =>  8.9167],

            // ================================================================
            // RIVERS STATE
            // ================================================================

            // Port Harcourt
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'Port Harcourt City', 'latitude' => 4.8156, 'longitude' => 7.0498],
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'GRA Phase 1',         'latitude' => 4.8333, 'longitude' => 7.0333],
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'GRA Phase 2',         'latitude' => 4.8500, 'longitude' => 7.0333],
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'Old GRA',             'latitude' => 4.8167, 'longitude' => 7.0333],
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'Rumuola',             'latitude' => 4.8333, 'longitude' => 7.0000],
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'Diobu',               'latitude' => 4.8167, 'longitude' => 7.0333],
            ['state' => 'Rivers', 'lga' => 'Port Harcourt', 'area' => 'Creek Road',          'latitude' => 4.7833, 'longitude' => 7.0167],

            // Obio/Akpor
            ['state' => 'Rivers', 'lga' => 'Obio/Akpor', 'area' => 'Rumuomasi',     'latitude' =>  4.8667, 'longitude' =>  7.0167],
            ['state' => 'Rivers', 'lga' => 'Obio/Akpor', 'area' => 'Rumuigbo',      'latitude' =>  4.8500, 'longitude' =>  7.0000],
            ['state' => 'Rivers', 'lga' => 'Obio/Akpor', 'area' => 'Rumuodara',     'latitude' =>  4.8833, 'longitude' =>  7.0167],
            ['state' => 'Rivers', 'lga' => 'Obio/Akpor', 'area' => 'Rumuola',       'latitude' =>  4.8333, 'longitude' =>  7.0000],
            ['state' => 'Rivers', 'lga' => 'Obio/Akpor', 'area' => 'Ozuoba',        'latitude' =>  4.9167, 'longitude' =>  7.0000],
            ['state' => 'Rivers', 'lga' => 'Obio/Akpor', 'area' => 'Eliozu',        'latitude' =>  4.9000, 'longitude' =>  7.0167],

            // Eleme
            ['state' => 'Rivers', 'lga' => 'Eleme', 'area' => 'Eleme Township',     'latitude' =>  4.7906, 'longitude' =>  7.1433],
            ['state' => 'Rivers', 'lga' => 'Eleme', 'area' => 'Ogale',              'latitude' =>  4.8167, 'longitude' =>  7.1167],

            // ================================================================
            // SOKOTO STATE
            // ================================================================

            // Sokoto North
            ['state' => 'Sokoto', 'lga' => 'Sokoto North', 'area' => 'Sokoto City', 'latitude' => 13.0622, 'longitude' =>  5.2339],
            ['state' => 'Sokoto', 'lga' => 'Sokoto North', 'area' => 'Gawon Nama',  'latitude' => 13.0833, 'longitude' =>  5.2500],
            ['state' => 'Sokoto', 'lga' => 'Sokoto North', 'area' => 'Arkilla',     'latitude' => 13.0667, 'longitude' =>  5.2333],

            // Sokoto South
            ['state' => 'Sokoto', 'lga' => 'Sokoto South', 'area' => 'Mabera',      'latitude' => 13.0333, 'longitude' =>  5.2167],
            ['state' => 'Sokoto', 'lga' => 'Sokoto South', 'area' => 'Tudun Wada Sokoto', 'latitude' => 13.0500, 'longitude' => 5.2000],
            ['state' => 'Sokoto', 'lga' => 'Sokoto South', 'area' => 'Kara',        'latitude' => 13.0167, 'longitude' =>  5.2333],

            // ================================================================
            // TARABA STATE
            // ================================================================

            // Jalingo
            ['state' => 'Taraba', 'lga' => 'Jalingo', 'area' => 'Jalingo Township', 'latitude' =>  8.8978, 'longitude' => 11.3642],
            ['state' => 'Taraba', 'lga' => 'Jalingo', 'area' => 'Sarkin Dawaki',    'latitude' =>  8.9000, 'longitude' => 11.3833],
            ['state' => 'Taraba', 'lga' => 'Jalingo', 'area' => 'Sintali',          'latitude' =>  8.8833, 'longitude' => 11.3500],

            // ================================================================
            // YOBE STATE
            // ================================================================

            // Damaturu
            ['state' => 'Yobe', 'lga' => 'Damaturu', 'area' => 'Damaturu Township','latitude' => 11.7472, 'longitude' => 11.9606],
            ['state' => 'Yobe', 'lga' => 'Damaturu', 'area' => 'Gwange Damaturu',  'latitude' => 11.7333, 'longitude' => 11.9500],
            ['state' => 'Yobe', 'lga' => 'Damaturu', 'area' => 'Pompomari',        'latitude' => 11.7667, 'longitude' => 11.9667],

            // ================================================================
            // ZAMFARA STATE
            // ================================================================

            // Gusau
            ['state' => 'Zamfara', 'lga' => 'Gusau', 'area' => 'Gusau Township',   'latitude' => 12.1696, 'longitude' =>  6.6649],
            ['state' => 'Zamfara', 'lga' => 'Gusau', 'area' => 'Sabon Gari Gusau', 'latitude' => 12.1833, 'longitude' =>  6.6500],
            ['state' => 'Zamfara', 'lga' => 'Gusau', 'area' => 'Tudun Wada Gusau', 'latitude' => 12.1667, 'longitude' =>  6.6333],

        ];

        foreach ($areas as $area) {
            DB::table('areaCoordinates')->updateOrInsert(
                [
                    'state' => $area['state'],
                    'lga'   => $area['lga'],
                    'area'  => $area['area'],
                ],
                array_merge($area, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Area coordinates seeded: ' . count($areas) . ' records.');
    }
}