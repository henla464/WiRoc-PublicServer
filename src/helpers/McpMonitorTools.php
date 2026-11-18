<?php

/**
 * Read-only monitoring tools exposed over MCP. Every method returns compact plain text.
 * All user-supplied strings are bound parameters; integers are clamped before SQL interpolation.
 */
class McpMonitorTools
{
    private $helperFactory;
    private $helper;
    /** null = the shared admin key (sees everything); ['userId'=>int,'isAdmin'=>bool] = a user's token */
    private $principal;

    public function __construct(callable $helperFactory, $principal = null) {
        $this->helperFactory = $helperFactory;
        $this->principal = $principal;
    }

    private function isUnrestricted() {
        return $this->principal === null || !empty($this->principal['isAdmin']);
    }

    private function visibleToUserId() {
        return $this->isUnrestricted() ? null : (int)$this->principal['userId'];
    }

    private function requireCompetitionAccess($competitionId) {
        if ($this->isUnrestricted()) return;
        if (!$this->helper()->UserHasCompetitionAccess($this->principal['userId'], false, $competitionId)) {
            throw new InvalidArgumentException("You do not have access to competition $competitionId");
        }
    }

    private function requireDeviceAccess($btAddress) {
        if ($this->isUnrestricted()) return;
        if (!$this->helper()->UserCanMonitorDevice($this->principal['userId'], $btAddress)) {
            throw new InvalidArgumentException("You do not have access to device $btAddress");
        }
    }

    private function helper() {
        if ($this->helper === null) {
            $factory = $this->helperFactory;
            $this->helper = $factory();
        }
        return $this->helper;
    }

    public static function definitions() {
        return [
            [
                'name' => 'list_competitions',
                'description' => 'List competitions on the WiRoc monitoring server (id, name, created time).',
                'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'additionalProperties' => false],
            ],
            [
                'name' => 'list_devices',
                'description' => 'One line per WiRoc device: BT address, name, control, battery, report/internet freshness, low-battery flag, public/LAN IP, versions. Use only_issues=true to see only devices that need attention.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'competition_id' => ['type' => 'integer', 'description' => 'Only devices assigned to this competition'],
                        'only_issues' => ['type' => 'boolean', 'description' => 'Only devices not reporting, without internet, low battery or battery below 20%', 'default' => false],
                        'limit' => ['type' => 'integer', 'description' => 'Max devices (default 100, max 500)', 'default' => 100],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_device',
                'description' => 'Full status of one device: identity, reporting/battery/IP state, last 5 status samples, aggregated message stats and current mesh state.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['bt_address' => ['type' => 'string', 'description' => 'Device BT address, e.g. AA:BB:CC:DD:EE:FF']],
                    'required' => ['bt_address'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_device_status_history',
                'description' => 'Recent DeviceStatus samples for one device (newest first): time, battery, SI station, unacked LoRa messages.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'bt_address' => ['type' => 'string'],
                        'minutes' => ['type' => 'integer', 'description' => 'Look-back window in minutes (default 60, max 1440)', 'default' => 60],
                        'limit' => ['type' => 'integer', 'description' => 'Max samples (default 100, max 500)', 'default' => 100],
                    ],
                    'required' => ['bt_address'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_mesh',
                'description' => 'LoRa mesh overview for a competition: snapshot freshness, root health (epoch, allocated addresses, overhead, ack timeout), node state counts, link count and detected issues. include_topology=true adds per-node lines.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'competition_id' => ['type' => 'integer'],
                        'include_topology' => ['type' => 'boolean', 'default' => false],
                        'max_nodes' => ['type' => 'integer', 'description' => 'Max node lines when include_topology=true (default 50, max 200)', 'default' => 50],
                    ],
                    'required' => ['competition_id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_monitoring_summary',
                'description' => 'Digest of the whole installation: device counts, devices not reporting / without internet / low battery, per-competition mesh health and message counters. Optionally scoped to one competition.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['competition_id' => ['type' => 'integer', 'description' => 'Scope to one competition']],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function call($name, $args) {
        if (!is_array($args)) $args = [];
        switch ($name) {
            case 'list_competitions': return $this->listCompetitions();
            case 'list_devices': return $this->listDevices($args);
            case 'get_device': return $this->getDevice($args);
            case 'get_device_status_history': return $this->getDeviceStatusHistory($args);
            case 'get_mesh': return $this->getMesh($args);
            case 'get_monitoring_summary': return $this->getMonitoringSummary($args);
            default: throw new InvalidArgumentException("Unknown tool: $name");
        }
    }

    private static function intArg($args, $key, $default, $min, $max) {
        $v = $args[$key] ?? $default;
        if (!is_numeric($v)) $v = $default;
        $v = (int)$v;
        return max($min, min($max, $v));
    }

    private static function boolArg($args, $key) {
        $v = $args[$key] ?? false;
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    private static function strArg($args, $key) {
        $v = $args[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    private static function secondsAgo($seconds) {
        if ($seconds === null || $seconds === '') return '-';
        $s = (int)$seconds;
        if ($s < 0) return 'now';
        if ($s < 60) return $s . 's';
        if ($s < 3600) return intdiv($s, 60) . 'm';
        if ($s < 86400) return intdiv($s, 3600) . 'h';
        return intdiv($s, 86400) . 'd';
    }

    private static function yesNo($v) {
        return $v ? 'yes' : 'no';
    }

    private static function isoOrDash($v) {
        return ($v === null || $v === '') ? '-' : $v;
    }

    private function listCompetitions() {
        $h = $this->helper();
        $rows = $this->isUnrestricted()
            ? $h->GetCompetitionsForUser(0, true)
            : $h->GetCompetitionsForUser($this->principal['userId'], false);
        if (!$rows) return $this->isUnrestricted() ? 'No competitions.' : 'No competitions you have access to.';
        $lines = ['Competitions (' . count($rows) . '):'];
        foreach ($rows as $r) {
            $lines[] = '#' . $r['id'] . ' ' . $r['name'] . ' (access ' . $r['accessRole'] . ', created ' . self::isoOrDash($r['createdTime']) . ')';
        }
        return implode("\n", $lines);
    }

    private function devicesWithAges($competitionId, $limit) {
        $h = $this->helper();
        $devices = $h->GetDevicesViewRows('', $competitionId === null ? '' : $competitionId, ' ORDER BY Devices.BTAddress', 'LIMIT ' . ($limit + 1), $this->visibleToUserId());
        $ages = [];
        foreach ($h->QueryAll("SELECT BTAddress, TIMESTAMPDIFF(SECOND, reportTime, NOW()) reportAge, TIMESTAMPDIFF(SECOND, connectedToInternetTime, NOW()) netAge FROM Devices") as $row) {
            $ages[$row['BTAddress']] = $row;
        }
        return [$devices, $ages];
    }

    private static function isTroubled($d) {
        $batt = (int)$d->batteryLevel;
        return !$d->recentlyReported || !$d->connectedToInternet || $d->batteryIsLow == 1 || ($batt > 0 && $batt < 20);
    }

    private function listDevices($args) {
        $competitionId = isset($args['competition_id']) ? self::intArg($args, 'competition_id', 0, 1, PHP_INT_MAX) : null;
        $onlyIssues = self::boolArg($args, 'only_issues');
        $limit = self::intArg($args, 'limit', 100, 1, 500);

        if ($competitionId !== null) $this->requireCompetitionAccess($competitionId);
        list($devices, $ages) = $this->devicesWithAges($competitionId, $limit);
        $truncated = count($devices) > $limit;
        if ($truncated) $devices = array_slice($devices, 0, $limit);

        if ($onlyIssues) $devices = array_values(array_filter($devices, function ($d) { return self::isTroubled($d); }));

        $header = 'Devices' . ($competitionId === null ? '' : ' (competition ' . $competitionId . ')') . ($onlyIssues ? ', issues only' : '') . ': ' . count($devices) . ' listed' . ($truncated ? ' (truncated at ' . $limit . ', narrow with competition_id)' : '');
        if (!$devices) return $header . "\nNo devices match.";

        $lines = [$header];
        foreach ($devices as $d) {
            $age = $ages[$d->BTAddress] ?? [];
            $batt = (int)$d->batteryLevel;
            $lines[] = $d->BTAddress
                . ' "' . $d->name . '"'
                . ' ctrl=' . self::isoOrDash($d->controlNumber)
                . ' comp=' . self::isoOrDash($d->competitionId)
                . ' batt=' . ($batt > 0 ? $batt . '%' : '?')
                . ' low=' . self::yesNo($d->batteryIsLow == 1)
                . ' rep=' . self::secondsAgo($age['reportAge'] ?? null)
                . ' net=' . self::secondsAgo($age['netAge'] ?? null)
                . ' ip=' . self::isoOrDash($d->publicIPAddress)
                . ' lanip=' . self::isoOrDash($d->localIPAddress)
                . ' v=' . self::isoOrDash($d->wirocPythonVersion);
        }
        return implode("\n", $lines);
    }

    private function getDevice($args) {
        $btAddress = self::strArg($args, 'bt_address');
        if ($btAddress === '') throw new InvalidArgumentException('bt_address is required');
        $this->requireDeviceAccess($btAddress);
        $h = $this->helper();

        $rows = $h->QueryAll("SELECT d.*,
                TIMESTAMPDIFF(SECOND, d.reportTime, NOW()) reportAge,
                TIMESTAMPDIFF(SECOND, d.connectedToInternetTime, NOW()) netAge,
                TIMESTAMPDIFF(SECOND, d.ipAddressTime, NOW()) ipAge,
                s.batteryLevel, s.createdTime batteryLevelTime,
                TIMESTAMPDIFF(SECOND, s.createdTime, NOW()) batteryAge
            FROM Devices d
            LEFT JOIN DeviceStatuses s ON s.Id = (SELECT Id FROM DeviceStatuses WHERE BTAddress = d.BTAddress ORDER BY createdTime DESC LIMIT 1)
            WHERE d.BTAddress = :btAddress", ['btAddress' => $btAddress]);
        if (!$rows) throw new InvalidArgumentException("No device with BTAddress $btAddress");
        $d = $rows[0];

        $lines = [];
        $lines[] = $d['BTAddress'] . ' "' . $d['name'] . '"';
        $lines[] = 'competition=' . self::isoOrDash($d['competitionId']) . ' control=' . self::isoOrDash($d['controlId']) . ' relayPathNo=' . self::isoOrDash($d['relayPathNo']) . ' description="' . self::isoOrDash($d['description']) . '"';
        $lines[] = 'reporting=' . self::yesNo($d['reportAge'] !== null) . ' (' . self::secondsAgo($d['reportAge']) . ' ago)'
            . ' internet=' . self::yesNo($d['netAge'] !== null) . ' (' . self::secondsAgo($d['netAge']) . ' ago)'
            . ' battery=' . ($d['batteryLevel'] === null ? '?' : $d['batteryLevel'] . '%') . ' (' . self::secondsAgo($d['batteryAge']) . ' ago)'
            . ' low=' . self::yesNo($d['batteryIsLow'] == 1);
        $lines[] = 'ip: public=' . self::isoOrDash($d['publicIPAddress'] ?? null) . ' lan=' . self::isoOrDash($d['localIPAddress'] ?? null) . ' updated=' . self::secondsAgo($d['ipAge'] ?? null) . ' ago';
        $lines[] = 'versions: python=' . self::isoOrDash($d['wirocPythonVersion']) . ' ble=' . self::isoOrDash($d['wirocBLEAPIVersion']) . ' hw=' . self::isoOrDash($d['hardwareVersion']);

        $statuses = $h->QueryAll("SELECT createdTime, batteryLevel, siStationNumber, noOfLoraMsgSentNotAcked, allLoraPunchesSentOK
            FROM DeviceStatuses WHERE BTAddress = :btAddress ORDER BY createdTime DESC LIMIT 5", ['btAddress' => $btAddress]);
        $lines[] = 'Last ' . count($statuses) . ' status sample(s):' . ($statuses ? '' : ' none');
        foreach ($statuses as $s) {
            $lines[] = '  ' . $s['createdTime'] . ' batt=' . self::isoOrDash($s['batteryLevel']) . '% si=' . self::isoOrDash($s['siStationNumber']) . ' notAcked=' . self::isoOrDash($s['noOfLoraMsgSentNotAcked']) . ' allPunchesOK=' . self::isoOrDash($s['allLoraPunchesSentOK']);
        }

        $stats = $h->QueryAll("SELECT MessageStats.adapterInstance, MessageStats.messageType,
                (SELECT ims.status FROM MessageStats ims WHERE ims.BTAddress = MessageStats.BTAddress
                    AND ims.adapterInstance = MessageStats.adapterInstance AND ims.messageType = MessageStats.messageType
                    ORDER BY ims.createdTime DESC LIMIT 1) AS status,
                SUM(MessageStats.noOfMessages) AS noOfMessages, MAX(MessageStats.updateTime) AS updateTime
            FROM MessageStats WHERE MessageStats.BTAddress = :btAddress
            GROUP BY MessageStats.BTAddress, MessageStats.adapterInstance, MessageStats.messageType
            ORDER BY MessageStats.adapterInstance, MessageStats.messageType", ['btAddress' => $btAddress]);
        $lines[] = 'Message stats (' . count($stats) . '):' . ($stats ? '' : ' none');
        foreach ($stats as $s) {
            $lines[] = '  ' . self::isoOrDash($s['adapterInstance']) . '/' . self::isoOrDash($s['messageType']) . ' status=' . self::isoOrDash($s['status']) . ' count=' . self::isoOrDash($s['noOfMessages']) . ' updated=' . self::isoOrDash($s['updateTime']);
        }

        $mesh = $h->QueryAll("SELECT * FROM MeshStates WHERE BTAddress = :btAddress", ['btAddress' => $btAddress]);
        if ($mesh) {
            $m = $mesh[0];
            $lines[] = 'Mesh state: addr=' . self::isoOrDash($m['addr']) . ' state=' . self::isoOrDash($m['state']) . ' parent=' . self::isoOrDash($m['parentAddr'])
                . ' hops=' . self::isoOrDash($m['hopCount']) . ' cost=' . self::isoOrDash($m['pathCost']) . ' neighbours=' . self::isoOrDash($m['neighbourCount'])
                . ' txq=' . self::isoOrDash($m['transmitQueue']) . ' txOk=' . self::isoOrDash($m['txOk']) . ' txBusy=' . self::isoOrDash($m['txBusy'])
                . ' alloc=' . self::isoOrDash($m['allocatedAddresses']) . ' epoch=' . self::isoOrDash($m['epoch']) . ' overhead=' . self::isoOrDash($m['overheadPercent']) . '%'
                . ' ackTimeout=' . self::isoOrDash($m['ackTimeoutMs']) . 'ms standby=' . self::yesNo($m['standby'] == 1)
                . ' (updated ' . self::isoOrDash($m['updateTime']) . ')';
        } else {
            $lines[] = 'Mesh state: none';
        }

        return implode("\n", $lines);
    }

    private function getDeviceStatusHistory($args) {
        $btAddress = self::strArg($args, 'bt_address');
        if ($btAddress === '') throw new InvalidArgumentException('bt_address is required');
        $minutes = self::intArg($args, 'minutes', 60, 1, 1440);
        $limit = self::intArg($args, 'limit', 100, 1, 500);
        $this->requireDeviceAccess($btAddress);

        $rows = $this->helper()->QueryAll("SELECT createdTime, batteryLevel, siStationNumber, noOfLoraMsgSentNotAcked, allLoraPunchesSentOK
            FROM DeviceStatuses WHERE BTAddress = :btAddress
              AND createdTime >= NOW() - INTERVAL $minutes MINUTE
            ORDER BY createdTime DESC LIMIT $limit", ['btAddress' => $btAddress]);

        $lines = ["Status history for $btAddress (last $minutes min, " . count($rows) . ' samples):'];
        if (!$rows) return $lines[0] . "\nNo samples in that window.";
        foreach ($rows as $s) {
            $lines[] = $s['createdTime'] . ' batt=' . self::isoOrDash($s['batteryLevel']) . '% si=' . self::isoOrDash($s['siStationNumber'])
                . ' notAcked=' . self::isoOrDash($s['noOfLoraMsgSentNotAcked']) . ' allPunchesOK=' . self::isoOrDash($s['allLoraPunchesSentOK']);
        }
        return implode("\n", $lines);
    }

    private function getMesh($args) {
        $competitionId = self::intArg($args, 'competition_id', 0, 1, PHP_INT_MAX);
        $includeTopology = self::boolArg($args, 'include_topology');
        $maxNodes = self::intArg($args, 'max_nodes', 50, 1, 200);
        $this->requireCompetitionAccess($competitionId);
        $h = $this->helper();

        $topo = $h->BuildCompetitionMeshTopology($competitionId);
        $lines = [];
        if ($topo['updatedTime'] === null) {
            return "Mesh competition $competitionId: no fresh root mesh snapshot (within 10 minutes).\n"
                . 'Check that the root device is running and reporting MeshTopology/MeshState.';
        }

        $ageRow = $h->QueryAll("SELECT TIMESTAMPDIFF(SECOND, MAX(m.updateTime), NOW()) ageSeconds
            FROM MeshTopologies m JOIN Devices d ON d.BTAddress = m.BTAddress
            WHERE d.competitionId = :competitionId AND m.isRoot = 1", ['competitionId' => $competitionId]);
        $age = $ageRow ? $ageRow[0]['ageSeconds'] : null;
        $lines[] = "Mesh competition $competitionId: fresh root snapshot, server age " . self::secondsAgo($age);

        $rootBT = null;
        $counts = [0 => 0, 1 => 0, 2 => 0, 'none' => 0];
        $maxCost = null;
        $unresolvedParent = [];
        foreach ($topo['nodes'] as $n) {
            if ($n['isRoot']) $rootBT = $n['BTAddress'];
            $key = $n['state'] === null ? 'none' : (int)$n['state'];
            if (!isset($counts[$key])) $counts[$key] = 0;
            $counts[$key]++;
            if ($n['pathCost'] !== null && ($maxCost === null || $n['pathCost'] > $maxCost)) $maxCost = $n['pathCost'];
            if (!$n['isRoot'] && $n['state'] === 2 && $n['parentBTAddress'] === null && $n['hopCount'] !== null && $n['hopCount'] > 1) {
                $unresolvedParent[] = $n['BTAddress'];
            }
        }

        $lines[] = 'root=' . self::isoOrDash($rootBT);
        $lines[] = 'health: ' . ($topo['health'] === null
            ? 'unknown (no fresh MeshState row for the root; epoch/overhead unavailable)'
            : 'epoch=' . self::isoOrDash($topo['health']['epoch'])
                . ' allocatedAddresses=' . self::isoOrDash($topo['health']['allocatedAddresses'])
                . ' overhead=' . self::isoOrDash($topo['health']['overheadPercent']) . '%'
                . ' ackTimeout=' . self::isoOrDash($topo['health']['ackTimeoutMs']) . 'ms');
        $lines[] = 'nodes=' . count($topo['nodes']) . ' (root=' . ($rootBT === null ? 0 : 1) . ', state2/joined=' . ($counts[2] ?? 0) . ', state1/joining=' . ($counts[1] ?? 0) . ', state0/unassigned=' . ($counts[0] ?? 0) . ', no-state=' . $counts['none'] . ')';
        $lines[] = 'links=' . count($topo['links']) . ', maxPathCost=' . self::isoOrDash($maxCost);

        $issues = [];
        if ($topo['health'] === null) $issues[] = 'root MeshState missing or stale';
        if (($counts[1] ?? 0) > 0) $issues[] = ($counts[1]) . ' node(s) joining (state=1)';
        if (($counts[0] ?? 0) > 0) $issues[] = ($counts[0]) . ' node(s) unassigned (state=0)';
        if ($counts['none'] > 0) $issues[] = $counts['none'] . ' node(s) with no fresh MeshState row';
        if ($unresolvedParent) $issues[] = 'parent unresolved for ' . implode(', ', $unresolvedParent);
        if ($topo['health'] !== null && $topo['health']['allocatedAddresses'] !== null && $topo['health']['allocatedAddresses'] > count($topo['nodes'])) {
            $issues[] = 'root reports ' . $topo['health']['allocatedAddresses'] . ' allocated addresses but only ' . count($topo['nodes']) . ' node(s) are drawable (devices without map position are hidden; may also be stale allocations)';
        }
        $lines[] = 'issues: ' . ($issues ? implode('; ', $issues) : 'none');

        if ($includeTopology) {
            $shown = array_slice($topo['nodes'], 0, $maxNodes);
            $lines[] = 'topology (' . count($shown) . ' of ' . count($topo['nodes']) . ' nodes, sorted by hop count):';
            foreach ($shown as $n) {
                $lines[] = '  hop=' . self::isoOrDash($n['hopCount']) . ' addr=' . $n['BTAddress'] . ' "' . $n['deviceName'] . '"'
                    . ' ctrl=' . self::isoOrDash($n['controlNumber'])
                    . ' state=' . self::isoOrDash($n['state'])
                    . ' parent=' . self::isoOrDash($n['parentBTAddress'])
                    . ' cost=' . self::isoOrDash($n['pathCost'])
                    . ' txq=' . self::isoOrDash($n['transmitQueue'])
                    . ' txOk=' . self::isoOrDash($n['txOk'])
                    . ' txBusy=' . self::isoOrDash($n['txBusy'])
                    . ' standby=' . self::yesNo($n['standby'])
                    . ' reported=' . self::yesNo($n['recentlyReported'])
                    . ' net=' . self::yesNo($n['connectedToInternet']);
            }
        }

        return implode("\n", $lines);
    }

    private function getMonitoringSummary($args) {
        $competitionId = isset($args['competition_id']) ? self::intArg($args, 'competition_id', 0, 1, PHP_INT_MAX) : null;
        if ($competitionId !== null) $this->requireCompetitionAccess($competitionId);
        $h = $this->helper();
        $now = $h->QueryAll("SELECT NOW() now");
        $lines = ['WiRoc monitoring summary' . ($competitionId === null ? ' (all competitions)' : ' (competition ' . $competitionId . ')')
            . ($this->isUnrestricted() ? '' : ', scoped to your access')
            . ' at ' . ($now ? $now[0]['now'] : '?')];

        list($devices, $ages) = $this->devicesWithAges($competitionId, 500);
        $notReporting = [];
        $noInternet = [];
        $lowBattery = [];
        $weakBattery = [];
        foreach ($devices as $d) {
            $age = $ages[$d->BTAddress] ?? [];
            $batt = (int)$d->batteryLevel;
            if (!$d->recentlyReported) $notReporting[] = $d->BTAddress . ' (' . self::secondsAgo($age['reportAge'] ?? null) . ' ago)';
            if (!$d->connectedToInternet) $noInternet[] = $d->BTAddress;
            if ($d->batteryIsLow == 1) $lowBattery[] = $d->BTAddress;
            elseif ($batt > 0 && $batt < 20) $weakBattery[] = $d->BTAddress . " ($batt%)";
        }
        $lines[] = 'devices: ' . count($devices) . ' total, ' . count($notReporting) . ' not reporting, ' . count($noInternet) . ' without internet, '
            . count($lowBattery) . ' low battery, ' . count($weakBattery) . ' below 20%' . (count($devices) >= 500 ? ' (500 shown, likely truncated)' : '');
        if ($notReporting) $lines[] = '  not reporting: ' . implode(', ', array_slice($notReporting, 0, 20));
        if ($lowBattery) $lines[] = '  low battery: ' . implode(', ', array_slice($lowBattery, 0, 20));
        if ($weakBattery) $lines[] = '  below 20%: ' . implode(', ', array_slice($weakBattery, 0, 20));

        $compSql = "SELECT DISTINCT c.id, c.name FROM Competitions c JOIN Devices d ON d.competitionId = c.id";
        $compArgs = [];
        $compConditions = [];
        if ($competitionId !== null) {
            $compConditions[] = 'c.id = :competitionId';
            $compArgs['competitionId'] = $competitionId;
        }
        if (!$this->isUnrestricted()) {
            $visibleIds = array_map(function ($c) { return (int)$c['id']; }, $h->GetCompetitionsForUser($this->principal['userId'], false));
            $compConditions[] = 'c.id IN (' . implode(',', $visibleIds ?: [0]) . ')';
        }
        if ($compConditions) $compSql .= ' WHERE ' . implode(' AND ', $compConditions);
        $compSql .= ' ORDER BY c.id';
        $competitions = $h->QueryAll($compSql, $compArgs);
        $shown = array_slice($competitions, 0, 10);
        $lines[] = 'mesh:';
        if (!$shown) $lines[] = '  no competitions with devices';
        foreach ($shown as $c) {
            $topo = $h->BuildCompetitionMeshTopology($c['id']);
            if ($topo['updatedTime'] === null) {
                $lines[] = '  #' . $c['id'] . ' ' . $c['name'] . ': no fresh root mesh snapshot';
                continue;
            }
            $health = $topo['health'];
            $lines[] = '  #' . $c['id'] . ' ' . $c['name'] . ': ' . count($topo['nodes']) . ' nodes, links=' . count($topo['links']) . ', '
                . ($health === null ? 'root health unknown' : 'root epoch=' . self::isoOrDash($health['epoch']) . ' allocated=' . self::isoOrDash($health['allocatedAddresses']) . ' overhead=' . self::isoOrDash($health['overheadPercent']) . '%');
        }
        if (count($competitions) > count($shown)) {
            $lines[] = '  (' . (count($competitions) - count($shown)) . ' more competition(s) not shown; pass competition_id)';
        }

        $stats = $h->QueryAll("SELECT COUNT(*) adapterRows, IFNULL(SUM(noOfMessages),0) noOfMessages FROM MessageStats WHERE updateTime >= NOW() - INTERVAL 60 MINUTE");
        $s = $stats ? $stats[0] : null;
        $lines[] = 'messages: ' . ($s ? $s['adapterRows'] : 0) . ' adapter counter(s) updated in the last 60 min, cumulative total ' . ($s ? $s['noOfMessages'] : 0) . ' (counters are cumulative per adapter/message type, not per hour)';

        return implode("\n", $lines);
    }
}
