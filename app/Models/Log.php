<?php

namespace App\Models;

use Adif\adif;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Log extends Model
{
    const BATCHSIZE = 2000;

    const COLUMNS = [
        'logNum' =>     ['lbl' =>   'Log',          'class' => ''],
        'conf_qc' =>    ['lbl' =>   'C',            'class' => 'r'],
        'myGsq' =>      ['lbl' =>   'My GSQ',       'class' => 'not-compact multi-qth'],
        'myQth' =>      ['lbl' =>   'My QTH',       'class' => 'not-compact multi-qth'],
        'date' =>       ['lbl' =>   'Date',         'class' => ''],
        'time' =>       ['lbl' =>   'UTC',          'class' => ''],
        'call' =>       ['lbl' =>   'Callsign',     'class' => ''],
        'qsoCount' =>   ['lbl' =>   'QSOs',         'class' => ''],
        'qsoBands' =>   ['lbl' =>   'QSO Bands',    'class' => 'not-compact'],
        'name' =>       ['lbl' =>   'Name',         'class' => 'not-compact'],
        'mode' =>       ['lbl' =>   'Mode',         'class' => ''],
        'band' =>       ['lbl' =>   'Band',         'class' => ''],
        'rx' =>         ['lbl' =>   'RX',           'class' => 'r'],
        'tx' =>         ['lbl' =>   'TX',           'class' => 'r'],
        'pwr' =>        ['lbl' =>   'Pwr',          'class' => 'r'],
        'qth' =>        ['lbl' =>   'Location',     'class' => ''],
        'countyName' => ['lbl' =>   'US County',    'class' => 'not-compact'],
        'sp' =>         ['lbl' =>   'S/P',          'class' => ''],
        'itu' =>        ['lbl' =>   'Country',      'class' => ''],
        'continent' =>  ['lbl' =>   'Cont',         'class' => ''],
        'gsq' =>        ['lbl' =>   'GSQ',          'class' => ''],
        'km' =>         ['lbl' =>   'Km',           'class' => 'r'],
        'deg' =>        ['lbl' =>   'Deg',          'class' => 'r not-compact'],
        'comment' =>    ['lbl' =>   'Comment',      'class' => 'r not-compact'],
    ];
    const US_COUNTIES = [
        'AK' => 27,
        'AL' => 67,
        'AR' => 75,
        'AZ' => 15,
        'CA' => 58,
        'CO' => 64,
        'CT' => 8,
        'DC' => 1,
        'DE' => 3,
        'FL' => 67,
        'GA' => 159,
        'GU' => 1,
        'HI' => 4,
        'IA' => 99,
        'ID' => 44,
        'IL' => 102,
        'IN' => 92,
        'KS' => 105,
        'KY' => 120,
        'LA' => 64,
        'MA' => 14,
        'MD' => 24,
        'ME' => 16,
        'MI' => 83,
        'MN' => 87,
        'MO' => 115,
        'MS' => 82,
        'MT' => 56,
        'NC' => 100,
        'ND' => 53,
        'NE' => 93,
        'NH' => 10,
        'NJ' => 21,
        'NM' => 33,
        'NV' => 17,
        'NY' => 62,
        'OH' => 88,
        'OK' => 77,
        'OR' => 36,
        'PA' => 67,
        'PR' => 78,
        'RI' => 5,
        'SC' => 46,
        'SD' => 66,
        'TN' => 95,
        'TX' => 254,
        'UT' => 29,
        'VA' => 132,
        'VI' => 3,
        'VT' => 16,
        'WA' => 39,
        'WI' => 72,
        'WV' => 55,
        'WY' => 23
    ];

    const GSQ_SUBSTITUTES = [
        3 => [
            'VK0IR' => 'MD66' // Heard Island operator who placed wrong GSQ in Antarctica mainland
        ]
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'qrzId',
        'userId',
        'date',
        'time',
        'call',
        'name',
        'band',
        'mode',
        'rx',
        'tx',
        'pwr',
        'qth',
        'county',
        'sp',
        'itu',
        'continent',
        'gsq',
        'km',
        'deg',
        'conf'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * @param $qthGSQ
     * @param $logGSQ
     * @return int|null
     */
    public static function calculateBearing($qthGSQ, $logGSQ)
    {
        $qth = static::convertGsqToDegrees($qthGSQ);
        $log = static::convertGsqToDegrees($logGSQ);
        if ($qth === false || $log === false) {
            return null;
        }
        if ($qth['lat'] === $log['lat'] && $qth['lon'] === $log['lon']) {
            return 0;
        }
        $qth_lat_r = deg2rad($qth['lat']);
        $qth_lon_r = deg2rad($qth['lon']);
        $log_lat_r = deg2rad($log['lat']);
        $log_lon_r = deg2rad($log['lon']);
        $diff_lon = ($log['lon'] - $qth['lon']);
        if (abs($diff_lon) > 180) {
            $diff_lon = (360 - abs($diff_lon)) * (0 - ($diff_lon / abs($diff_lon)));
        }
        $diff_lon_r = deg2rad($diff_lon);
        $deg = (
            rad2deg(
                atan2(
                    SIN($log_lon_r - $qth_lon_r) * COS($log_lat_r),
                    COS($qth_lat_r) * SIN($log_lat_r) - SIN($qth_lat_r) * COS($log_lat_r) * COS($diff_lon_r)
                )
            ) + 360
        ) % 360;
        return $deg;
    }

    /**
     * @param $GSQ
     * @return false|array
     */
    public static function convertGsqToDegrees($GSQ): bool|array
    {
        $regExp =
            '/^(?:[a-rA-R]{2}[0-9]{2}|'                                 // FN03
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}|'                          // FN03HR
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}[0-9]{2}|'                  // FN03HR72
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}[0-9]{2}[a-xA-X]{2})$/i';   // FN03HR72VO

        if (!preg_match($regExp, $GSQ)) {
            return false;
        }
        $_GSQ =      strToUpper($GSQ);
        if (strlen($_GSQ) === 4) {
            $_GSQ = $_GSQ."LL";
        }
        if (strlen($_GSQ) === 6) {
            $_GSQ = $_GSQ."55";
        }
        if (strlen($_GSQ) === 8) {
            $_GSQ = $_GSQ."XX";
        }
        $lat=
            (ord($_GSQ[1])-65) * 10 - 90 +
            (ord($_GSQ[3])-48) +
            (ord($_GSQ[5])-65) / 24 +
            (ord($_GSQ[7])-48) / 240 +
            (ord($_GSQ[9])-65) / 5760;
        $lon=
            (ord($_GSQ[0])-65) * 20 - 180 +
            (ord($_GSQ[2])-48) * 2 +
            (ord($_GSQ[4])-65) / 12 +
            (ord($_GSQ[6])-48) / 120 +
            (ord($_GSQ[8])-65) / 2880;

        return [
            "gsq" => $GSQ,
            "lat" => round($lat, 4),
            "lon" => round($lon, 4)
        ];
    }

    public static function deleteLogsForUser(User $user): bool
    {
        return Log::where('userId', $user['id'])->delete();
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getBandsForUser(User $user): array
    {
        $items = Log::distinct()->where('userId', $user['id'])->get(['band'])->toArray();
        $out = [];
        foreach ($items as $item) {
            $b =        $item['band'];
            $num =      preg_replace('/[^0-9]/', '', $b);
            $units =    preg_replace('/[^a-zA-Z]/', '', $b);
            $v =        ($num ? $num * (strtolower($units) === 'm' ? 100 : 1) : $b);
            $out[$v] =  $item['band'];
        }
        krsort($out);
        return array_values($out);
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getDBLogsForUser(User $user): array
    {
        $hide = [];
        $locations = User::getQthNamesForUser($user);
        foreach ($locations as $gsq => $name) {
            if (strtoupper($name) === 'HIDE') {
                $hide[] = $gsq;
            }
        }
        $logs = Log::Select(
            'logs.band',
            DB::raw("
                    (SELECT
                        COUNT(DISTINCT `band`)
                    FROM
                        `logs` `l`
                    WHERE
                        `l`.`userId` = `logs`.`userId`
                        AND `l`.`call` = `logs`.`call`
                    ) `bandCount`"
            ),
            'logs.call',
            'logs.comment',
            'logs.conf',
            'logs.clublog_conf',
            'logs.continent',
            'logs.county',
            'logs.date',
            'logs.deg',
            'logs.gsq',
            'logs.itu',
            'logs.km',
            'logs.logNum',
            'logs.mode',
            'logs.myGsq',
            'logs.myQth',
            'logs.name',
            'logs.pwr',
            DB::raw("
                (SELECT
                    GROUP_CONCAT(`l`.`band`)
                FROM
                    `logs` `l`
                WHERE
                    `l`.`userId` = `logs`.`userId`
                    AND `l`.`call` = `logs`.`call`
                ) `qsos`"
            ),
            DB::raw("
                    (SELECT
                        COUNT(*)
                    FROM
                        `logs` `l`
                    WHERE
                        `l`.`userId` = `logs`.`userId`
                        AND `l`.`call` = `logs`.`call`
                    ) `qsoCount`"
            ),
            'logs.qth',
            'logs.rx',
            'logs.sp',
            'logs.time',
            'logs.tx',
            'iso3166.flag'
        )->leftJoin(
            'iso3166',
            'logs.itu',
            '=',
            'iso3166.country'
        )
            ->where('userId', $user->id)
            ->whereNotIn('myGsq', $hide)
            ->orderBy('time', 'asc')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
//        $sql = Str::replaceArray('?', $query->getBindings(), $query->toSql());
//        print_r($sql);die;
        foreach ($logs as &$log) {
            $log['conf_qc'] = ($log['conf'] === 'Y' ? '1' : ($log['clublog_conf'] === 'Y' ? '2' : ''));
        }
        return $logs;
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getGsqsForUser(User $user): array
    {
        $items = Log::distinct()->where('userId', $user['id'])->get(['myGsq'])->toArray();
        $out = [];
        foreach($items as $item) {
            $out[] = $item['myGsq'];
        }
        sort($out);
        return $out;
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getLogQthsForUser(User $user): array
    {
        $existing = User::getQthNamesForUser($user);
        $rows =
            Log::Select(
                'logs.myGsq',
                'logs.myQth'
            )
            ->groupBy('myGsq','myQth')
            ->where('userId', $user->id)
            ->orderBy('myGsq', 'asc')
            ->orderBy('myQth', 'asc')
            ->get()
            ->toArray();
        foreach ($rows as &$item) {
            if (isset($existing[$item['myGsq']]) && $existing[$item['myGsq']] !== $item['myQth']) {
                $item['myQth'] = $existing[$item['myGsq']];
            }
        }
        $result = [];
        foreach ($rows as $row) {
            $result[$row['myGsq']] = $row['myQth'];
        }
        return $result;
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getLogsForUser(User $user): array
    {
        if ($user->active && (
                !$user->qrz_last_data_pull
                || $user->qrz_last_data_pull->addMinutes(getEnv('LOGS_MAX_AGE'))->isPast()
            )) {
            static::getQRZDataForUser($user);
        }
        return Log::getDBLogsForUser($user);
    }

    public static function getLogCountriesForUser(User $user): array
    {
        return Iso3166::Select(
                'iso3166.country',
                DB::raw("
                    (SELECT
                        COUNT(DISTINCT (itu))
                    FROM
                        `logs` `l`
                    WHERE
                        `l`.`itu` = `iso3166`.`country`
                        AND `userId` = " . (int)$user->id . ") AS logged"
                ),
                DB::raw("
                    (SELECT
                        COUNT(DISTINCT (itu))
                    FROM
                        `logs` `l`
                    WHERE
                        `l`.`itu` = `iso3166`.`country`
                        AND `conf` = 'Y'
                        AND `userId` = " . (int)$user->id . ") AS confirmed"
                )
            )
            ->orderBy('country')
            ->get()
            ->toArray();
    }

    public static function getLogUsStateCountiesForUser(User $user): array
    {
        $debug = false;
        if ($debug) {
            DB::enableQueryLog();
        }
        $states =
            State::Select(
                'states.country',
                'states.sp',
                DB::raw("
                    (SELECT
                        COUNT(DISTINCT(county))
                    FROM
                        `logs` `l`
                    WHERE
                        `userId` = " . (int)$user->id . "
                        AND `county` <> ''
                        AND `l`.`itu` = `states`.`country`
                        AND (`l`.`itu` IN('Alaska', 'Guam', 'Guam', 'Hawaii', 'Puerto Rico', 'US Virgin Islands') OR `l`.`sp` = `states`.`sp`)
                        AND SUBSTR(`l`.`county`, 1, 2) = `l`.`sp`
                    ) AS logged"
                ),
                DB::raw("
                    (SELECT
                        COUNT(DISTINCT(county))
                    FROM
                        `logs` `l`
                    WHERE
                        `userId` = " . (int)$user->id . "
                        AND `county` <> ''
                        AND `l`.`itu` = `states`.`country`
                        AND (`l`.`itu` IN('Alaska', 'Guam', 'Hawaii', 'Puerto Rico', 'US Virgin Islands') OR `l`.`sp` = `states`.`sp`)
                        AND SUBSTR(`l`.`county`, 1, 2) = `l`.`sp`
                        AND `conf` = 'Y'
                    ) AS confirmed
                "),
                DB::raw("
                    (SELECT
                        COUNT(DISTINCT(county))
                    FROM
                        `logs` `l`
                    WHERE
                        `userId` = " . (int)$user->id . "
                        AND `county` <> ''
                        AND `l`.`itu` = `states`.`country`
                        AND (`l`.`itu` IN('Alaska', 'Guam', 'Hawaii', 'Puerto Rico', 'US Virgin Islands') OR `l`.`sp` = `states`.`sp`)
                        AND SUBSTR(`l`.`county`, 1, 2) != `l`.`sp`
                    ) AS wrongSpCount"
                ),
                DB::raw("
                    (SELECT
                        GROUP_CONCAT(
                            CONCAT(' * ', `date`, ' ', `time`, ' ', `call`, ' ', `band`, '\n    SP:', `sp`, ' ITU:', `itu`, ' County:', county, '\n    Fixable? ', IF(LENGTH(myGsq)<=8, 'Yes', 'No (myGsq too long for QRZ.com edits)')
                        ) SEPARATOR '\n\n')
                    FROM
                        `logs` `l`
                    WHERE
                        `userId` = " . (int)$user->id . "
                        AND `county` <> ''
                        AND `l`.`itu` = `states`.`country`
                        AND (`l`.`itu` IN('Alaska', 'Guam', 'Hawaii', 'Puerto Rico', 'US Virgin Islands') OR `l`.`sp` = `states`.`sp`)
                        AND SUBSTR(`l`.`county`, 1, 2) != `l`.`sp`
                    ) AS wrongSpLogs"
                ),
            )
            ->whereIn('country', ['USA', 'Alaska', 'Guam', 'Hawaii', 'Puerto Rico', 'US Virgin Islands'])
            ->orderBy('sp')
            ->get()
            ->toArray();
        if ($debug) {
            print_r(DB::getQueryLog());
        }
        $results = [];
        foreach ($states as $state) {
            $sp = $state['sp'];
            switch ($state['country']) {
                case 'Alaska':
                    $sp = 'AK';
                    break;
                case 'Guam':
                    $sp = 'GU';
                    break;
                case 'Hawaii':
                    $sp = 'HI';
                    break;
                case 'Puerto Rico':
                    $sp = 'PR';
                    break;
                case 'US Virgin Islands':
                    $sp = 'VI';
                    break;
            }
            $results[] = [
                'sp' =>             $sp,
                'itu' =>            $state['country'],
                'logged' =>         $state['logged'],
                'confirmed' =>      $state['confirmed'],
                'wrongSpCount' =>   $state['wrongSpCount'],
                'wrongSpLogs' =>    $state['wrongSpLogs'],
                'total' =>          Log::US_COUNTIES[$sp],
                'percent' =>        (int)round(100 * ($state['confirmed'] / Log::US_COUNTIES[$sp]))
            ];
        }
        usort($results, function ($a, $b) {
            return $a['sp'] <=> $b['sp'];
        });
        return $results;
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getModesForUser(User $user): array
    {
        $items = Log::distinct()->where('userId', $user['id'])->get(['mode'])->toArray();
        $out = [];
        foreach($items as $item) {
            $out[] = $item['mode'];
        }
        sort($out);
        return $out;
    }

    /**
     * @param User $user
     * @param string $option
     * @return array
     */
    public static function getQRZDataFromServer(User $user, string $option)
    {
        $url = static::getQRZEndpoint($user['qrz_api_key'], 'FETCH', $option);
        try {
            $raw = file_get_contents($url);
        } catch (\Exception $e) {
            $user->setAttribute('qrz_last_result', 'Server Error - ' . self::sanitizeErrorMessage($e->getMessage()));
            $user->save();
            return [];
        }
        if (str_contains($raw, 'RESULT=FAIL') && str_contains($raw, 'COUNT=0')) {
            $user->setAttribute('qrz_last_result', 'OK');
            $user->setAttribute('qrz_last_data_pull', time());
            $user->save();
            return [];
        }
        if (!(substr($raw, 0, 5) === 'ADIF=' || str_contains($raw, 'RESULT=OK'))) {
            $user->setAttribute('qrz_last_result', substr($raw, 0, 250));
            $user->save();
            return [];
        }
        $data = str_replace(['&lt;', '&gt;'], ['<', '>'], $raw);

        $adif = new adif(trim('<EOH>' . $data));
        return $adif->parser();
    }

    /**
     * @param User $user
     * @return bool|void
     */
    public static function getQRZDataForUser(User $user)
    {
        $debug = [];
        $isIncremental = $user['qrz_last_data_pull'] !== null;
        ini_set('max_execution_time', 600);
        if (!self::getQRZStatusFromServer($user)) {
            return;
        }
        if ($isIncremental) {
            $dateNow = date('Y-m-d');
            $dateFrom = Carbon::parse($user['qrz_last_data_pull'])->subDay(1)->format('Y-m-d');
            $qrzItems1 = self::getQRZDataFromServer($user, 'BETWEEN:' . $dateFrom . '+' . $dateNow);
            $qrzItems2 = self::getQRZDataFromServer($user, 'MODSINCE:' . $dateFrom);
            $qrzItems = array_merge($qrzItems1, $qrzItems2);
            $debug[] = "QRZ changes $dateFrom to $dateNow";
        } else {
            $qrzItems = self::getQRZDataFromServer($user, 'MAX:' . self::BATCHSIZE . ',AFTERLOGID:' . $user->last_log_id ?? 0);
            $debug[] = "QRZ records for all time";
        }
        if (!$qrzItems) {
            $debug[] = "No records - exiting";
            $user->setAttribute('qrz_last_data_pull_debug', implode("\n", $debug));
            $user->setAttribute('qrz_last_data_pull', time());
            $user->save();
            return true;
        }
        $debug[] = "Records found: " . count($qrzItems);
        if (!$items = self::parseQrzLogData($user, $qrzItems)) {
            $debug[] = "No parseable records - exiting";
            $user->setAttribute('qrz_last_data_pull_debug', implode("\n", $debug));
            $user->save();
            return false;
        }
        $debug[] = "Records parsed: " . count($qrzItems);
        if (count($qrzItems) < self::BATCHSIZE) {
            $isIncremental = true;
        }
        try {
            $lastLogId = self::insertOrUpdateLogs($items);
            $user->setAttribute('last_log_id', $lastLogId);
            self::renumberLogsForUser($user);
            if ($isIncremental) {
                $user->setAttribute('qrz_last_data_pull', time());
            }
            User::updateStats($user);
            $user->setAttribute('qrz_last_data_pull_debug', implode("\n", $debug));
            $user->save();
            return true;
        } catch (\Exception $e) {
            $user->setAttribute('qrz_last_result', 'Server Error - ' . substr($e->getMessage(), 0, 240));
            $debug[] = "Error:" . substr($e->getMessage(), 0, 240);
            $user->setAttribute('qrz_last_data_pull_debug', substr(implode("\n", $debug), 0, 65300));
            $user->save();
            return false;
        }
    }

    /**
     * @param string $apikey
     * @param string $action
     * @param string|null $option
     * @return string
     */
    private static function getQRZEndpoint(string $apikey, string $action, string $option = null) {
        if ($option) {
            return sprintf("https://logbook.qrz.com/api?KEY=%s&ACTION=%s&OPTION=%s", $apikey, $action, $option);
        }
        return sprintf("https://logbook.qrz.com/api?KEY=%s&ACTION=%s", $apikey, $action);
    }

    /**
     * @param User $user
     * @return array|false
     */
    public static function getQRZStatusFromServer(User $user)
    {
        try {
            $url = static::getQRZEndpoint($user['qrz_api_key'], 'STATUS');
            $raw = file_get_contents($url);
        } catch (\Exception $e) {
            $user->setAttribute('qrz_last_result', 'Server Error - ' . self::sanitizeErrorMessage($e->getMessage()));
            $user->save();
            return false;
        }
        $status = [];
        $pairs = explode('&', $raw);
        foreach ($pairs as $pair) {
            list($key, $value) = explode('=', $pair, 2);
            $status[$key] = $value;
        }
        if ($status['RESULT'] === "OK") {
            return $status;
        }
        if (str_contains($status['REASON'], 'invalid api key')) {
            $user->setAttribute('qrz_last_result', 'Invalid QRZ Key');
            $user->save();
            return false;
        }
        if (str_contains($status['REASON'], 'user does not have a valid QRZ subscription')) {
            $user->setAttribute('qrz_last_result', 'Not XML Subscriber');
            $user->save();
            return false;
        }
        if ($status['CALLSIGN'] !== $user->call) {
            $user->setAttribute('qrz_last_result', 'Wrong Call for key');
            $user->save();
            return false;
        }
        $user->setAttribute('qrz_last_result', 'QRZ Server error');
        $user->save();
        return false;
    }

    /**
     * @param User $user
     * @return array
     */
    public static function getQthsForUser(User $user, $withAssociated = false): array
    {
        $identities = [];
        if ($withAssociated) {
            $query = DB::select(
                "SELECT `id`, `call` FROM `users` where SUBSTRING_INDEX(`call`, '/', 1) = ?",
                [explode('/', $user->call)[0]]
            );
            foreach ($query as $q) {
                $identities[$q->id] = $q->call;
            }
        } else {
            $identities[$user->id] = $user->call;
        }
        $hideGsqs = User::getHideGsqsForUser($user);
        $hideGsqPlaceholders = array_fill(0, count($hideGsqs), '?');

        $userIds = array_keys($identities);
        $userIdPlaceholders = array_fill(0, count($userIds), '?');

        $sql = "
            SELECT
                COUNT(distinct logs.id) as logCount,
                MIN(date) as logFirst,
                MAX(date) as logLast,
                COUNT(DISTINCT date) as logDays,
                COUNT(DISTINCT logs.band) as logBands,
                GROUP_CONCAT(DISTINCT bc.bandCount order by CAST(bandCount AS FLOAT) DESC) as logBandNames,
                logs.userId,
                logs.myGsq,
                logs.myQth,
                logs.program,
                logs.locId,
                logs.altProgram,
                logs.altLocId
            FROM
                logs
            INNER JOIN (
                SELECT
                    userId,
                    myGsq,
                    myQth,
                    CONCAT(band, '|', COUNT(*)) AS bandCount
                FROM
                    logs
                GROUP BY
                    userId,
                    myGsq,
                    myQth,
                    band
                ) bc ON
                bc.userId = logs.userId AND
                bc.myGsq = logs.myGsq AND
                bc.myQth = logs.myQth
            WHERE
                logs.userId IN(" . implode(',', $userIdPlaceholders) . ")
                " .($hideGsqs ? " AND logs.myGsq NOT IN(" . implode(',', $hideGsqPlaceholders) . ")" : "") . "
            GROUP BY
                userId,
                myQth,
                myGsq,
                program,
                locId,
                altProgram,
                altLocId
            ";
        if (is_string($sql)) {
            $items = DB::select(
                $sql,
                [ ...$userIds, ...$hideGsqs ]
            );
        }
        $out = [];
        $myQthNames = [];
        foreach($items as $item) {
            $myQthNames[$item->myQth] = true;
        }
        foreach($items as $item) {
            $latlon = self::convertGsqToDegrees($item->myGsq);
            $lat = $latlon['lat'];
            $lon = $latlon['lon'];
            $out[$item->myQth] = [
                'userId' =>         $item->userId,
                'call' =>           $identities[$item->userId],
                'gsq' =>            $item->myGsq,
                'home' =>           $lat === $user['lat'] && $lon === $user['lon'] || count(array_keys($myQthNames)) === 1,
                'lat' =>            $latlon['lat'],
                'lon' =>            $latlon['lon'],
                'logs' =>           $item->logCount,
                'logBands' =>       $item->logBands,
                'logBandNames' =>   $item->logBandNames,
                'logDays' =>        $item->logDays,
                'logFirst' =>       $item->logFirst,
                'logLast' =>        $item->logLast,
                'pota' =>           $item->program === 'POTA' ? $item->locId : ($item->altProgram === 'POTA' ? $item->altLocId : ''),
                'wwff' =>           $item->program === 'WWFF' ? $item->locId : ($item->altProgram === 'WWFF' ? $item->altLocId : ''),
            ];
        }
        ksort($out);
        return $out;
    }

    /**
     * @param $logs
     * @return void
     */
    public static function insertOrUpdateLogs($logs) {
        foreach ($logs as $item) {
            $log = Log::where('qrzId', '=', $item['qrzId'])->first();
            if ($log) {
                $log->update($item);
            } else {
                Log::insert($item);
            }
        }
        return $item['qrzId'];
    }

    /**
     * @param User $user
     * @param $qrzItems
     * @return false
     */
    public static function parseQrzLogData(User $user, $qrzItems): array|bool
    {
        $qthNames = User::getQthNamesForUser($user);
        $items = [];
        $altLocIds = ['POTA' => [], 'WWFF' => []];
        foreach ($qrzItems as $i) {
            if (!isset($i['APP_QRZLOG_LOGID'])) {
                continue;
            }
            if (isset($i['MY_GRIDSQUARE'])
                && isset($qthNames[strtoupper($i['MY_GRIDSQUARE'])])
                && $qthNames[strtoupper($i['MY_GRIDSQUARE'])] === 'HIDE'
            ) {
                continue;
            }
            try {
                $itu =      $i['COUNTRY'];
                $county =   $i['CNTY'] ?? '';
                switch ($itu) {
                    case 'Australia':
                    case 'Canada':
                        $county =   '';
                        $sp =       $i['STATE'] ?? '';
                        break;
                    case 'Alaska':
                        $sp = 'AK';
                        break;
                    case 'Guam':
                        $sp = 'GU';
                        break;
                    case 'Hawaii':
                        $sp = 'HI';
                        break;
                    case 'Puerto Rico':
                        $sp = 'PR';
                        break;
                    case 'US Virgin Islands':
                        $sp = 'VI';
                        break;
                    case 'United States':
                        $itu =  'USA';
                        $sp =   $i['STATE'] ?? '';
                        break;
                    default:
                        $sp = '';
                        $county = '';
                        break;
                }
                $log_gsq =          strtoupper($i['GRIDSQUARE'] ?? '');
                if (isset(self::GSQ_SUBSTITUTES[$user['id']][$i['CALL']])) {
                    $log_gsq = self::GSQ_SUBSTITUTES[$user['id']][$i['CALL']];
                }
                $my_gsq =           strtoupper($i['MY_GRIDSQUARE'] ?? $user['gsq']);
                $my_qth =           $i['MY_CITY'] ?? $user['qth'];
                if (isset($qthNames[$my_gsq])) {
                    $my_qth = $qthNames[$my_gsq];
                }
                $deg =              Log::calculateBearing($my_gsq, $log_gsq);
                $name =             $i['NAME'] ?? '';
                $name =             (strtoupper($name) === $name ? ucwords(strtolower($name)) : $name);
                preg_match("/(POTA|WWFF): ([A-Z]+-[0-9]+)/i", $my_qth, $loc_bits);
                $program =          $loc_bits[1] ?? '';
                $locId =            $loc_bits[2] ?? '';
                if ($program && !isset($altLocIds[$program][$locId])) {
                    $altLocIds[$program][$locId] = [];
                }
                $items[] = [
                    'userId' =>     $user['id'],
                    'qrzId' =>      $i['APP_QRZLOG_LOGID'],
                    'myGsq' =>      $my_gsq,
                    'myQth' =>      $my_qth,
                    'program' =>    $program,
                    'locId' =>      $locId,
                    'date' =>       substr($i['QSO_DATE'], 0, 4) . '-' . substr($i['QSO_DATE'], 4, 2) . '-' . substr($i['QSO_DATE'], 6, 2),
                    'time' =>       substr($i['TIME_ON'], 0, 2) . ':' . substr($i['TIME_ON'], 2, 2),
                    'call' =>       $i['CALL'],
                    'name' =>       $name,
                    'band' =>       strtolower($i['BAND']),
                    'mode' =>       $i['MODE'] ?? '',
                    'rx' =>         $i['RST_RCVD'] ?? '',
                    'tx' =>         $i['RST_SENT'] ?? '',
                    'pwr' =>        $i['TX_PWR'] ?? '',
                    'qth' =>        $i['QTH'] ?? '',
                    'county' =>     $county,
                    'sp' =>         $sp,
                    'itu' =>        $itu,
                    'continent' =>  strtoupper($i['CONT'] ?? ''),
                    'gsq' =>        substr(strtoupper($log_gsq), 0, 4),
                    'km' =>         $i['DISTANCE'] ?? null,
                    'deg' =>        $deg,
                    'comment' =>    ($i['COMMENT'] ?? ''),
                    'conf' =>       ($i['APP_QRZLOG_STATUS'] ?? '') === 'C' ? 'Y' : ''
                ];
            } catch (\Exception $e) {
                $user->setAttribute('qrz_last_result', 'Server Error - ' . substr($e->getMessage(), 0, 240));
                $user->save();
                return false;
            }
        }
        $pota_refs = array_keys($altLocIds['POTA']);
        sort($pota_refs);
        $results = ParkMatch::select(['ref1','ref2'])->wherein('ref1', $pota_refs)->get()->toArray();
        foreach ($results as $result) {
            $altLocIds['POTA'][$result['ref1']] = ['WWFF', $result['ref2']];
        }

        $wwff_refs = array_keys($altLocIds['WWFF']);
        sort($wwff_refs);
        $results = ParkMatch::select(['ref1','ref2'])->wherein('ref2', $wwff_refs)->get()->toArray();
        foreach ($results as $result) {
            $altLocIds['WWFF'][$result['ref2']] = ['POTA', $result['ref1']];
        }

        usort($items, function ($a, $b) {
            return $a['date'] . $a['time'] <=> $b['date'] . $b['time'];
        });

        foreach ($items as &$item) {
            $item['altProgram'] = $altLocIds[$item['program']][$item['locId']][0] ?? null;
            $item['altLocId'] = $altLocIds[$item['program']][$item['locId']][1] ?? null;
        }

        return $items;
    }

    /**
     * @param User $user
     * @return void
     */
    public static function renumberLogsForUser(User $user) {
        // Renumber logNum values
        DB::statement('SET @logNum := 0;');
        DB::statement(
            'UPDATE logs SET logNum = @logNum := @logNum+1 WHERE userId=? ORDER BY `date` ASC, `time` ASC',
            [$user->id]
        );
    }

    /**
     * @param $message
     * @return string
     */
    private static function sanitizeErrorMessage($message)
    {
        return substr(
            preg_replace('/KEY=([^\&]*)\&/', 'KEY=XXXX-XXXX-XXXX-XXXX&', html_entity_decode($message)),
            0,
            240
        );
    }
}
