<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with host prototypes.
 */
class CHostPrototype extends CHostBase {

	protected $sortColumns = ['hostid', 'host', 'name', 'status', 'discover'];

	/**
	 * Get host prototypes.
	 *
	 * @param array        $options
	 * @param bool         $options['selectMacros']      Array of macros fields to be selected or string "extend".
	 * @param string|array $options['selectInterfaces']  Return an "interfaces" property with host interfaces.
	 *
	 * @return array
	 */
	public function get(array $options) {
		$hosts_fields = array_keys($this->getTableSchema('hosts')['fields']);
		$output_fields = ['hostid', 'host', 'name', 'status', 'templateid', 'inventory_mode', 'discover',
			'custom_interfaces', 'uuid'
		];
		$link_fields = ['group_prototypeid', 'groupid', 'hostid', 'templateid'];
		$group_fields = ['group_prototypeid', 'name', 'hostid', 'templateid'];
		$discovery_fields = array_keys($this->getTableSchema('items')['fields']);
		$hostmacro_fields = array_keys($this->getTableSchema('hostmacro')['fields']);
		$interface_fields = ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'discoveryids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'hostid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'host' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
				'templateid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'inventory_mode' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'host' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => 'inventory_mode,'.implode(',', $output_fields), 'default' => $output_fields],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'groupCount' =>				['type' => API_FLAG, 'default' => false],
			'selectGroupLinks' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $link_fields), 'default' => null],
			'selectGroupPrototypes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $group_fields), 'default' => null],
			'selectDiscoveryRule' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $discovery_fields), 'default' => null],
			'selectParentHost' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $hosts_fields), 'default' => null],
			'selectInterfaces' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $interface_fields), 'default' => null],
			'selectTemplates' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $hosts_fields), 'default' => null],
			'selectMacros' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $hostmacro_fields), 'default' => null],
			'selectTags' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['tag', 'value']), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'inherited'	=>				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>			['type' => API_BOOLEAN, 'default' => false]	// TODO: This property and frontend usage SHOULD BE removed.
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $output_fields;
		}

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = [];
		while ($row = DBfetch($res)) {
			// a count query, return a single result
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $row;
				}
				else {
					$result = $row['rowscount'];
				}
			}
			// a normal select query
			else {
				$result[$row[$this->pk()]] = $row;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['triggerid'], $options['output']);
		}

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput'] && $this->outputIsRequested('inventory_mode', $options['output'])) {
			$sqlParts['select']['inventory_mode'] =
				dbConditionCoalesce('hinv.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode');
		}

		if ((!$options['countOutput'] && $this->outputIsRequested('inventory_mode', $options['output']))
				|| ($options['filter'] && array_key_exists('inventory_mode', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'hinv', 'table' => 'host_inventory', 'using' => 'hostid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		return $sqlParts;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// do not return host prototypes from discovered hosts
		$sqlParts['from'][] = 'host_discovery hd';
		$sqlParts['from'][] = 'items i';
		$sqlParts['from'][] = 'hosts ph';
		$sqlParts['where'][] = $this->fieldId('hostid').'=hd.hostid';
		$sqlParts['where'][] = 'hd.parent_itemid=i.itemid';
		$sqlParts['where'][] = 'i.hostid=ph.hostid';
		$sqlParts['where'][] = 'ph.flags='.ZBX_FLAG_DISCOVERY_NORMAL;

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.
					'host_discovery hd,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId(self::$userData['userid'])).
				' WHERE h.hostid=hd.hostid'.
					' AND hd.parent_itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
				' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// discoveryids
		if ($options['discoveryids'] !== null) {
			$sqlParts['where'][] = dbConditionInt('hd.parent_itemid', (array) $options['discoveryids']);

			if ($options['groupCount']) {
				$sqlParts['group']['hd'] = 'hd.parent_itemid';
			}
		}

		// inherited
		if ($options['inherited'] !== null) {
			$sqlParts['where'][] = ($options['inherited']) ? 'h.templateid IS NOT NULL' : 'h.templateid IS NULL';
		}

		if ($options['filter'] && array_key_exists('inventory_mode', $options['filter'])) {
			if ($options['filter']['inventory_mode'] !== null) {
				$inventory_mode_query = (array) $options['filter']['inventory_mode'];

				$inventory_mode_where = [];
				$null_position = array_search(HOST_INVENTORY_DISABLED, $inventory_mode_query);

				if ($null_position !== false) {
					unset($inventory_mode_query[$null_position]);
					$inventory_mode_where[] = 'hinv.inventory_mode IS NULL';
				}

				if ($null_position === false || $inventory_mode_query) {
					$inventory_mode_where[] = dbConditionInt('hinv.inventory_mode', $inventory_mode_query);
				}

				$sqlParts['where'][] = (count($inventory_mode_where) > 1)
					? '('.implode(' OR ', $inventory_mode_where).')'
					: $inventory_mode_where[0];
			}
		}

		return $sqlParts;
	}

	/**
	 * Retrieves and adds additional requested data to the result set.
	 *
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostPrototypeIds = array_keys($result);

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'parent_itemid', 'host_discovery');
			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding group links
		if ($options['selectGroupLinks'] !== null && $options['selectGroupLinks'] != API_OUTPUT_COUNT) {
			$groupPrototypes = DBFetchArray(DBselect(
				'SELECT hg.group_prototypeid,hg.hostid'.
					' FROM group_prototype hg'.
					' WHERE '.dbConditionInt('hg.hostid', $hostPrototypeIds).
					' AND hg.groupid IS NOT NULL'
			));
			$relationMap = $this->createRelationMap($groupPrototypes, 'hostid', 'group_prototypeid');
			$groupPrototypes = API::getApiService()->select('group_prototype', [
				'output' => $options['selectGroupLinks'],
				'group_prototypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			foreach ($groupPrototypes as &$groupPrototype) {
				unset($groupPrototype['name']);
			}
			unset($groupPrototype);
			$result = $relationMap->mapMany($result, $groupPrototypes, 'groupLinks');
		}

		// adding group prototypes
		if ($options['selectGroupPrototypes'] !== null && $options['selectGroupPrototypes'] != API_OUTPUT_COUNT) {
			$groupPrototypes = DBFetchArray(DBselect(
				'SELECT hg.group_prototypeid,hg.hostid'.
				' FROM group_prototype hg'.
				' WHERE '.dbConditionInt('hg.hostid', $hostPrototypeIds).
					' AND hg.groupid IS NULL'
			));
			$relationMap = $this->createRelationMap($groupPrototypes, 'hostid', 'group_prototypeid');
			$groupPrototypes = API::getApiService()->select('group_prototype', [
				'output' => $options['selectGroupPrototypes'],
				'group_prototypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			foreach ($groupPrototypes as &$groupPrototype) {
				unset($groupPrototype['groupid']);
			}
			unset($groupPrototype);
			$result = $relationMap->mapMany($result, $groupPrototypes, 'groupPrototypes');
		}

		// adding host
		if ($options['selectParentHost'] !== null && $options['selectParentHost'] != API_OUTPUT_COUNT) {
			$hosts = [];
			$relationMap = new CRelationMap();
			$dbRules = DBselect(
				'SELECT hd.hostid,i.hostid AS parent_hostid'.
					' FROM host_discovery hd,items i'.
					' WHERE '.dbConditionInt('hd.hostid', $hostPrototypeIds).
					' AND hd.parent_itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['hostid'], $relation['parent_hostid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$hosts = API::Host()->get([
					'output' => $options['selectParentHost'],
					'hostids' => $related_ids,
					'templated_hosts' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapOne($result, $hosts, 'parentHost');
		}

		// adding templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectTemplates'],
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $templates, 'templates');
			}
			else {
				$templates = API::Template()->get([
					'hostids' => $hostPrototypeIds,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['templates'] = array_key_exists($hostid, $templates)
						? $templates[$hostid]['rowscount']
						: '0';
				}
			}
		}

		// adding tags
		if ($options['selectTags'] !== null && $options['selectTags'] !== API_OUTPUT_COUNT) {
			$tags = API::getApiService()->select('host_tag', [
				'output' => $this->outputExtend($options['selectTags'], ['hostid', 'hosttagid']),
				'filter' => ['hostid' => $hostPrototypeIds],
				'preservekeys' => true
			]);

			$relation_map = $this->createRelationMap($tags, 'hostid', 'hosttagid');
			$tags = $this->unsetExtraFields($tags, ['hostid', 'hosttagid'], []);
			$result = $relation_map->mapMany($result, $tags, 'tags');
		}

		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
			$interfaces = API::HostInterface()->get([
				'output' => $this->outputExtend($options['selectInterfaces'], ['hostid', 'interfaceid']),
				'hostids' => $hostPrototypeIds,
				'sortfield' => 'interfaceid',
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach (array_keys($result) as $hostid) {
				$result[$hostid]['interfaces'] = [];
			}

			foreach ($interfaces as $interface) {
				$hostid = $interface['hostid'];
				unset($interface['hostid'], $interface['interfaceid']);
				$result[$hostid]['interfaces'][] = $interface;
			}
		}

		return $result;
	}

	/**
	 * Creates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function create(array $host_prototypes) {
		$this->validateCreate($host_prototypes);

		$this->createReal($host_prototypes);
		$this->inherit($host_prototypes);

		return ['hostids' => array_column($host_prototypes, 'hostid')];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$host_prototypes): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['uuid'], ['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'uuid' =>				['type' => API_UUID],
			'ruleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]), 'default' => DB::getDefault('hosts', 'custom_interfaces')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]), 'default' => DB::getDefault('hosts', 'status')],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), 'default' => DB::getDefault('hosts', 'discover')],
			'interfaces' =>			self::getInterfacesCreateValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('group_prototype', 'name')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($host_prototypes);
		self::checkDiscoveryRules($host_prototypes);
		self::checkGroupLinks($host_prototypes);
		self::checkAndAddUuid($host_prototypes);
		self::checkMainInterfaces($host_prototypes);
		$this->checkTemplates($host_prototypes);
	}

	/**
	 * Creates the host prototypes and inherits them to linked hosts and templates.
	 *
	 * @param array $host_prototypes
	 * @param bool  $inherited
	 */
	protected function createReal(array &$host_prototypes, bool $inherited = false): void {
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		unset($host_prototype);

		$host_prototypeids = DB::insert($this->tableName(), $host_prototypes);

		foreach ($host_prototypes as $index => &$host_prototype) {
			$host_prototype['hostid'] = $host_prototypeids[$index];
		}
		unset($host_prototype);

		if (!$inherited) {
			$this->checkTemplatesLinks($host_prototypes);
		}

		self::createHostDiscoveries($host_prototypes);

		self::updateInterfaces($host_prototypes);
		self::updateGroupLinks($host_prototypes);
		self::updateGroupPrototypes($host_prototypes);
		$this->updateTemplates($host_prototypes);
		$this->updateTagsNew($host_prototypes);
		$this->updateMacros($host_prototypes);
		self::updateHostInventories($host_prototypes);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes);
	}

	/**
	 * Updates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function update(array $host_prototypes): array {
		$this->validateUpdate($host_prototypes, $db_host_prototypes);

		$this->updateReal($host_prototypes, $db_host_prototypes);
		$this->inherit($host_prototypes);

		return ['hostids' => array_column($host_prototypes, 'hostid')];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$host_prototypes, array &$db_host_prototypes = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'custom_interfaces' =>	['type' => API_INT32, 'in' => implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM])],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'discover' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])],
			'interfaces' =>			self::getInterfacesUpdateValidationRules(),
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('group_prototype', 'name')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
				'hostmacroid' =>		['type' => API_ID],
				'macro' =>				['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'inventory_mode' =>		['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_host_prototypes = $this->getAffectedObjects($host_prototypes);

		$host_prototypes = $this->extendObjectsByKey($host_prototypes, $db_host_prototypes, 'hostid',
			['host', 'name', 'custom_interfaces', 'ruleid']
		);

		self::populateInterfaces($host_prototypes, $db_host_prototypes);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'ruleid' =>	['type' => API_ID],
			'host' =>	['type' => API_H_NAME],
			'name' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($host_prototypes, $db_host_prototypes);
		self::checkGroupLinks($host_prototypes, $db_host_prototypes);
		self::validateSnmpInterfaces($host_prototypes, $db_host_prototypes);
		self::checkMainInterfaces($host_prototypes);
		$this->checkTemplates($host_prototypes, $db_host_prototypes);
		$this->checkTemplatesLinks($host_prototypes, $db_host_prototypes);
		$host_prototypes = parent::validateHostMacros($host_prototypes, $db_host_prototypes);
	}

	/**
	 * Updates the host prototypes and propagates the changes to linked hosts and templates.
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	protected function updateReal(array &$host_prototypes, array $db_host_prototypes): void {
		$upd_host_prototypes = [];

		// save the host prototypes
		foreach ($host_prototypes as $host_prototype) {
			$upd_host_prototype = DB::getUpdatedValues('hosts', $host_prototype,
				$db_host_prototypes[$host_prototype['hostid']]
			);

			if ($upd_host_prototype) {
				$upd_host_prototypes[] = [
					'values' => $upd_host_prototype,
					'where' => ['hostid' => $host_prototype['hostid']]
				];
			}
		}

		if ($upd_host_prototypes) {
			DB::update('hosts', $upd_host_prototypes);
		}

		self::updateInterfaces($host_prototypes, $db_host_prototypes);
		self::updateGroupLinks($host_prototypes, $db_host_prototypes);
		self::updateGroupPrototypes($host_prototypes, $db_host_prototypes);
		$this->updateTemplates($host_prototypes, $db_host_prototypes);
		$this->updateTagsNew($host_prototypes, $db_host_prototypes);
		$this->updateMacros($host_prototypes, $db_host_prototypes);
		self::updateHostInventories($host_prototypes, $db_host_prototypes);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST_PROTOTYPE, $host_prototypes,
			$db_host_prototypes
		);
	}

	/**
	 * Updates the children of the host prototypes on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array      $host_prototypes  array of host prototypes to inherit
	 * @param array|null $hostids          array of hosts to inherit to; if set to null, the children will be updated
	 *                                     on all child hosts
	 */
	protected function inherit(array $host_prototypes, array $hostids = null): void {
		if (!$host_prototypes) {
			return;
		}

		// prepare the child host prototypes
		$new_host_prototypes = $this->prepareInheritedObjects($host_prototypes, $hostids);
		if (!$new_host_prototypes) {
			return;
		}

		$ins_host_prototypes = [];
		$upd_host_prototypes = [];
		foreach ($new_host_prototypes as $new_host_prototype) {
			if (array_key_exists('hostid', $new_host_prototype)) {
				$upd_host_prototypes[] = $new_host_prototype;
			}
			else {
				$ins_host_prototypes[] = $new_host_prototype;
			}
		}

		// save the new host prototypes
		if ($ins_host_prototypes) {
			$this->createReal($ins_host_prototypes, true);
		}

		if ($upd_host_prototypes) {
			// Getting correct db objects for child host prototypes.
			$db_host_prototypes = $this->getAffectedObjects($upd_host_prototypes, false);

			$this->updateReal($upd_host_prototypes, $db_host_prototypes);
		}

		$host_prototypes = array_merge($upd_host_prototypes, $ins_host_prototypes);

		if ($host_prototypes) {
			$sql = 'SELECT hd.hostid'.
					' FROM host_discovery hd,items i,hosts h'.
					' WHERE hd.parent_itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_TEMPLATE.
						' AND '.dbConditionInt('hd.hostid', array_column($host_prototypes, 'hostid'));
			$valid_prototypes = DBfetchArrayAssoc(DBselect($sql), 'hostid');

			foreach ($host_prototypes as $key => $host_prototype) {
				if (!array_key_exists($host_prototype['hostid'], $valid_prototypes)) {
					unset($host_prototypes[$key]);
				}
			}
		}

		$this->inherit($host_prototypes);
	}

	/**
	 * Prepares and returns an array of child host prototypes, inherited from host prototypes $host_prototypes
	 * on the given hosts.
	 *
	 * Each host prototype must have the "ruleid" parameter set.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $hostids
	 *
	 * @return array  an array of unsaved child host prototypes
	 */
	protected function prepareInheritedObjects(array $host_prototypes, array $hostids = null) {
		// Fetch the related discovery rules with their hosts.
		$discovery_rules = DBfetchArrayAssoc(DBselect(
			'SELECT i.itemid,i.hostid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', array_keys(array_column($host_prototypes, null, 'ruleid'))).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE]).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		), 'itemid');

		if (!$discovery_rules) {
			return [];
		}

		// Remove host prototypes which don't belong to templates, so they cannot be inherited.
		$host_prototypes = array_filter($host_prototypes,
			static function (array $host_prototype) use ($discovery_rules): bool {
				return array_key_exists($host_prototype['ruleid'], $discovery_rules);
			}
		);

		// Fetch all child hosts to inherit to. Do not inherit host prototypes on discovered hosts.
		$child_hosts = API::Host()->get([
			'output' => ['hostid'],
			'selectParentTemplates' => ['templateid'],
			'templateids' => array_keys(array_column($discovery_rules, null, 'hostid')),
			'hostids' => $hostids,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'templated_hosts' => true,
			'nopermissions' => true
		]);

		if (!$child_hosts) {
			return [];
		}

		// Fetch the child discovery rules.
		$child_discovery_rules = API::DiscoveryRule()->get([
			'output' => ['itemid', 'templateid', 'hostid', 'name'],
			'filter' => [
				'templateid' => array_keys($discovery_rules)
			],
			'nopermissions' => true,
			'preservekeys' => true
		]);

		foreach ($child_discovery_rules as &$child_discovery_rule) {
			$child_discovery_rule['hostPrototypes'] = [];
		}
		unset($child_discovery_rule);

		/*
		 * Fetch child host prototypes and group them by discovery rule. "selectInterfaces" is not required, because
		 * all child are rewritten when updating parents.
		 */
		$child_host_prototypes = API::HostPrototype()->get([
			'output' => ['hostid', 'host', 'templateid'],
			'selectGroupLinks' => ['group_prototypeid', 'groupid', 'templateid'],
			'selectGroupPrototypes' => ['group_prototypeid', 'name', 'templateid'],
			'selectDiscoveryRule' => ['itemid'],
			'selectMacros' => ['hostmacroid', 'macro', 'type', 'value', 'description'],
			'discoveryids' => array_keys($child_discovery_rules),
			'nopermissions' => true
		]);

		foreach ($child_host_prototypes as $child_host_prototype) {
			$ruleid = $child_host_prototype['discoveryRule']['itemid'];
			unset($child_host_prototype['discoveryRule']);

			$child_discovery_rules[$ruleid]['hostPrototypes'][] = $child_host_prototype;
		}

		// Match each discovery that the parent host prototypes belong to to the child discovery rule for each host.
		$discovery_rule_links = [];
		foreach ($child_discovery_rules as $child_discovery_rule) {
			$discovery_rule_links[$child_discovery_rule['templateid']][$child_discovery_rule['hostid']]
				= $child_discovery_rule['itemid'];
		}

		$new_host_prototypes = [];

		foreach ($child_hosts as $child_host) {
			$hostid = $child_host['hostid'];
			$templateids = array_column($child_host['parentTemplates'], null, 'templateid');

			// Skip items not from parent templates of current host.
			$parent_host_prototypes = [];
			foreach ($host_prototypes as $inum => $host_prototype) {
				$templateid = $discovery_rules[$host_prototype['ruleid']]['hostid'];

				if (array_key_exists($templateid, $templateids)) {
					$parent_host_prototypes[$inum] = $host_prototype;
				}
			}

			foreach ($parent_host_prototypes as $parent_host_prototype) {
				$child_discovery_ruleid = $discovery_rule_links[$parent_host_prototype['ruleid']][$hostid];
				$child_discovery_rule = $child_discovery_rules[$child_discovery_ruleid];
				$ex_host_prototypes = $child_discovery_rule['hostPrototypes'];
				$ex_host_prototype = null;

				// Check if the child discovery rule already has host prototypes.
				if ($ex_host_prototypes) {
					$ex_host_prototypes_by_host = array_column($ex_host_prototypes, null, 'host');
					$ex_host_prototypes_by_templateid = array_column($ex_host_prototypes, null, 'templateid');

					// Look for an already created inherited host prototype. If one exists - update it.
					if (array_key_exists($parent_host_prototype['hostid'], $ex_host_prototypes_by_templateid)) {
						$ex_host_prototype = $ex_host_prototypes_by_templateid[$parent_host_prototype['hostid']];

						// Check if there's a host prototype on the target host with the same host name.
						if (array_key_exists($parent_host_prototype['host'], $ex_host_prototypes_by_host)
								&& bccomp($ex_host_prototypes_by_host[$parent_host_prototype['host']]['hostid'],
									$ex_host_prototype['hostid']) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Host prototype "%1$s" already exists on "%2$s".', $parent_host_prototype['host'],
									$child_discovery_rule['name']
								)
							);
						}
					}

					// Look for a host prototype with the same host name. If one exists - convert it to an inherited
					// host prototype.
					if ($ex_host_prototype === null
							&& array_key_exists($parent_host_prototype['host'], $ex_host_prototypes_by_host)) {
						$ex_host_prototype = $ex_host_prototypes_by_host[$parent_host_prototype['host']];
					}
				}

				// copy host prototype
				$new_host_prototype = $parent_host_prototype;
				$new_host_prototype['uuid'] = '';
				$new_host_prototype['ruleid'] = $child_discovery_ruleid;
				$new_host_prototype['templateid'] = $parent_host_prototype['hostid'];

				if (array_key_exists('macros', $new_host_prototype)) {
					foreach ($new_host_prototype['macros'] as &$hostmacro) {
						unset($hostmacro['hostmacroid']);
					}
					unset($hostmacro);
				}

				if (array_key_exists('interfaces', $new_host_prototype)) {
					foreach ($new_host_prototype['interfaces'] as &$interface) {
						unset($interface['interfaceid']);
					}
					unset($interface);
				}

				// Update an existing inherited host prototype.
				if ($ex_host_prototype) {
					// Look for existing group prototypes to update.
					$ex_gproto_by_templateid = array_column($ex_host_prototype['groupPrototypes'], null, 'templateid');
					$ex_gproto_by_name = array_column($ex_host_prototype['groupPrototypes'], null, 'name');
					$ex_macros_by_macro = array_column($ex_host_prototype['macros'], null, 'macro');

					// Look for a group prototype that can be updated.
					foreach ($new_host_prototype['groupPrototypes'] as &$group_prototype) {
						// Update an inherited group prototype by templateid.
						if (array_key_exists($group_prototype['group_prototypeid'], $ex_gproto_by_templateid)) {
							$group_prototype['group_prototypeid']
								= $ex_gproto_by_templateid[$group_prototype['group_prototypeid']]['group_prototypeid'];
						}
						// Update an inherited group prototype by name.
						elseif (array_key_exists($group_prototype['name'], $ex_gproto_by_name)) {
							$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
							$group_prototype['group_prototypeid']
								= $ex_gproto_by_name[$group_prototype['name']]['group_prototypeid'];
						}
						// Create a new child group prototype.
						else {
							$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
							unset($group_prototype['group_prototypeid']);
						}
					}
					unset($group_prototype);

					// Look for existing group links to update.
					$ex_glink_by_templateid = array_column($ex_host_prototype['groupLinks'], null, 'templateid');
					$ex_glink_by_groupid = array_column($ex_host_prototype['groupLinks'], null, 'groupid');

					// Look for a group link that can be updated.
					foreach ($new_host_prototype['groupLinks'] as &$group_link) {
						// Update an inherited group link by templateid.
						if (array_key_exists($group_link['group_prototypeid'], $ex_glink_by_templateid)) {
							$group_link['group_prototypeid']
								= $ex_glink_by_templateid[$group_link['group_prototypeid']]['group_prototypeid'];
						}
						// Update an inherited group link by groupid.
						elseif (array_key_exists($group_link['groupid'], $ex_glink_by_groupid)) {
							$group_link['templateid'] = $group_link['group_prototypeid'];
							$group_link['group_prototypeid']
								= $ex_glink_by_groupid[$group_link['groupid']]['group_prototypeid'];
						}
						// Create a new child group link.
						else {
							$group_link['templateid'] = $group_link['group_prototypeid'];
							unset($group_link['group_prototypeid']);
						}
					}
					unset($group_link);

					// Look for existing macros to update.
					if (array_key_exists('macros', $new_host_prototype)) {
						foreach ($new_host_prototype['macros'] as &$macro) {
							if (array_key_exists($macro['macro'], $ex_macros_by_macro)) {
								// Look for existing macro to set correct hostmacroid.
								$macro['hostmacroid'] = $ex_macros_by_macro[$macro['macro']]['hostmacroid'];
							}
						}
						unset($macro);
					}

					$new_host_prototype['hostid'] = $ex_host_prototype['hostid'];
				}
				// create a new inherited host prototype
				else {
					foreach ($new_host_prototype['groupPrototypes'] as &$group_prototype) {
						$group_prototype['templateid'] = $group_prototype['group_prototypeid'];
						unset($group_prototype['group_prototypeid']);
					}
					unset($group_prototype);

					foreach ($new_host_prototype['groupLinks'] as &$group_link) {
						$group_link['templateid'] = $group_link['group_prototypeid'];
						unset($group_link['group_prototypeid']);
					}
					unset($group_link);

					unset($new_host_prototype['hostid']);
				}

				$new_host_prototypes[] = $new_host_prototype;
			}
		}

		return $new_host_prototypes;
	}

	/**
	 * Inherits all host prototypes from the templates given in "templateids" to hosts or templates given in "hostids".
	 *
	 * @param array $data
	 */
	public function syncTemplates(array $data): void {
		$db_discovery_rules = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $data['templateids'],
			'nopermissions' => true
		]);

		$db_host_prototypes = $this->get([
			'output' => ['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectTags' => ['tag', 'value'],
			'selectTemplates' => ['templateid'],
			'selectDiscoveryRule' => ['itemid'],
			'selectInterfaces' => ['main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'discoveryids' => array_column($db_discovery_rules, 'itemid'),
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$db_host_prototypes = $this->getHostMacros($db_host_prototypes);

		foreach ($db_host_prototypes as &$db_host_prototype) {
			$db_host_prototype['ruleid'] = $db_host_prototype['discoveryRule']['itemid'];
			unset($db_host_prototype['discoveryRule']);
		}
		unset($db_host_prototype);

		$this->inherit($db_host_prototypes, $data['hostids']);
	}

	/**
	 * @param array $host_prototypeids
	 */
	public static function deleteForce(array $host_prototypeids): void {
		$host_prototypeids = array_merge($host_prototypeids, self::getChildIds($host_prototypeids));

		// Lock host prototypes before delete to prevent server from adding new LLD hosts.
		$db_host_prototypes = DBfetchArray(DBselect(
			'SELECT hostid,host'.
			' FROM hosts h'.
			' WHERE '.dbConditionId('h.hostid', $host_prototypeids).
			' FOR UPDATE'
		));

		$db_hosts = DB::select('host_discovery', [
			'output' => [],
			'filter' => [
				'parent_hostid' => $host_prototypeids
			],
			'preservekeys' => true
		]);

		if ($db_hosts) {
			API::Host()->delete(array_keys($db_hosts), true);
		}

		$db_group_prototypes = DB::select('group_prototype', [
			'output' => [],
			'filter' => [
				'hostid' => $host_prototypeids
			],
			'preservekeys' => true
		]);

		if ($db_group_prototypes) {
			self::deleteGroupPrototypes(array_keys($db_group_prototypes));
		}

		DB::delete('hosts', ['hostid' => $host_prototypeids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST_PROTOTYPE, $db_host_prototypes);
	}

	/**
	 * Delete host prototypes.
	 *
	 * @param array $host_prototypeids
	 *
	 * @return array
	 */
	public function delete(array $host_prototypeids): array {
		$this->validateDelete($host_prototypeids);

		self::deleteForce($host_prototypeids);

		return ['hostids' => $host_prototypeids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $host_prototypeids
	 *
	 * @throws APIException  if the input is invalid
	 */
	private function validateDelete(array &$host_prototypeids) : void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $host_prototypeids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'hostids' => $host_prototypeids,
			'editable' => true
		]);

		if ($count != count($host_prototypeids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_host_prototype = DBfetch(DBSelect(
			'SELECT h.hostid'.
			' FROM hosts h'.
			' WHERE h.templateid>0'.
				' AND '.dbConditionInt('h.hostid', $host_prototypeids),
			1
		));

		if ($db_host_prototype) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated host prototype.'));
		}
	}

	/**
	 * @param array $host_prototypeids
	 *
	 * @return array
	 */
	private static function getChildIds(array $host_prototypeids): array {
		$child_host_prototypeids = [];

		do {
			$db_host_prototypes = DB::select('hosts', [
				'output' => [],
				'filter' => [
					'templateid' => $host_prototypeids
				],
				'preservekeys' => true
			]);

			$host_prototypeids = array_keys($db_host_prototypes);
			$child_host_prototypeids += $db_host_prototypes;
		}
		while ($host_prototypeids);

		return array_keys($child_host_prototypeids);
	}

	/**
	 * Check for unique host prototype names.
	 *
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 *
	 * @throws APIException if host prototype names are not unique.
	 */
	private static function checkDuplicates(array $host_prototypes, array $db_host_prototypes = null): void {
		$h_names = [];
		$v_names = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('host', $host_prototype)) {
				if ($db_host_prototypes === null
						|| $host_prototype['host'] !== $db_host_prototypes[$host_prototype['hostid']]['host']) {
					$h_names[$host_prototype['ruleid']][] = $host_prototype['host'];
				}
			}

			if (array_key_exists('name', $host_prototype)) {
				if ($db_host_prototypes === null
						|| $host_prototype['name'] !== $db_host_prototypes[$host_prototype['hostid']]['name']) {
					$v_names[$host_prototype['ruleid']][] = $host_prototype['name'];
				}
			}
		}

		if ($h_names) {
			$where = [];
			foreach ($h_names as $ruleid => $names) {
				$where[] = '('.dbConditionInt('i.itemid', [$ruleid]).' AND '.dbConditionString('h.host', $names).')';
			}

			$duplicates = DBfetchArray(DBselect(
				'SELECT i.name AS rule,h.host'.
				' FROM items i,host_discovery hd,hosts h'.
				' WHERE i.itemid=hd.parent_itemid'.
					' AND hd.hostid=h.hostid'.
					' AND ('.implode(' OR ', $where).')',
				1
			));

			if ($duplicates) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host prototype with host name "%1$s" already exists in discovery rule "%2$s".',
					$duplicates[0]['host'], $duplicates[0]['rule']
				));
			}
		}

		if ($v_names) {
			$where = [];
			foreach ($v_names as $ruleid => $names) {
				$where[] = '('.dbConditionInt('i.itemid', [$ruleid]).' AND '.dbConditionString('h.name', $names).')';
			}

			$duplicates = DBfetchArray(DBselect(
				'SELECT i.name AS rule,h.name'.
				' FROM items i,host_discovery hd,hosts h'.
				' WHERE i.itemid=hd.parent_itemid'.
					' AND hd.hostid=h.hostid'.
					' AND ('.implode(' OR ', $where).')',
				1
			));

			if ($duplicates) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host prototype with visible name "%1$s" already exists in discovery rule "%2$s".',
					$duplicates[0]['host'], $duplicates[0]['rule']
				));
			}
		}
	}

	/**
	 * Check that only host prototypes on templates have UUID. Add UUID to all host prototypes on templates,
	 * if it doesn't exist.
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException
	 */
	private static function checkAndAddUuid(array &$host_prototypes): void {
		$templated_ruleids = DBfetchColumn(DBselect(
			'SELECT i.itemid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
			' AND '.dbConditionInt('i.itemid', array_unique(array_column($host_prototypes, 'ruleid'))).
			' AND h.status='.HOST_STATUS_TEMPLATE
		), 'itemid');

		foreach ($host_prototypes as $index => &$host_prototype) {
			if (!in_array($host_prototype['ruleid'], $templated_ruleids) && array_key_exists('uuid', $host_prototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/' . ($index + 1), _s('unexpected parameter "%1$s"', 'uuid'))
				);
			}

			if (in_array($host_prototype['ruleid'], $templated_ruleids) && !array_key_exists('uuid', $host_prototype)) {
				$host_prototype['uuid'] = generateUuidV4();
			}
		}
		unset($host_prototype);

		$duplicates = DB::select('hosts', [
			'output' => ['uuid'],
			'filter' => [
				'uuid' => array_column($host_prototypes, 'uuid')
			],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Entry with UUID "%1$s" already exists.', $duplicates[0]['uuid'])
			);
		}
	}

	/**
	 * Checks if the current user has access to the given LLD rules.
	 *
	 * @param array $host_prototypes
	 *
	 * @throws APIException if the user doesn't have write permissions for the given LLD rules
	 */
	private static function checkDiscoveryRules(array $host_prototypes): void {
		$ruleids = array_keys(array_flip(array_column($host_prototypes, 'ruleid')));

		$count = API::DiscoveryRule()->get([
			'countOutput' => true,
			'itemids' => $ruleids,
			'editable' => true
		]);

		if ($count != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Check if the host is discovered.
		$db_hosts = DBfetchArray(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', $ruleids).
				' AND h.flags='.ZBX_FLAG_DISCOVERY_CREATED,
			1
		));

		if ($db_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot create a host prototype on a discovered host "%1$s".', $db_hosts[0]['host'])
			);
		}
	}

	/**
	 * Check for valid host groups.
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 *
	 * @throws APIException if groups are not valid.
	 */
	private static function checkGroupLinks(array $host_prototypes, array $db_host_prototypes = null): void {
		$edit_groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			$groupids = array_column($host_prototype['groupLinks'], 'groupid');

			if ($db_host_prototypes === null) {
				$edit_groupids += array_flip($groupids);
			}
			else {
				$db_groupids = array_column($db_host_prototypes[$host_prototype['hostid']]['groupLinks'], 'groupid');

				$ins_groupids = array_flip(array_diff($groupids, $db_groupids));
				$del_groupids = array_flip(array_diff($db_groupids, $groupids));

				$edit_groupids += $ins_groupids + $del_groupids;
			}
		}

		if (!$edit_groupids) {
			return;
		}

		$db_groups = API::HostGroup()->get([
			'output' => ['name', 'flags'],
			'groupids' => array_keys($edit_groupids),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_groups) != count($edit_groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Check if group prototypes use discovered host groups.
		foreach ($db_groups as $db_group) {
			if ($db_group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group prototype cannot be based on a discovered host group "%1$s".', $db_group['name'])
				);
			}
		}
	}

	/**
	 * Check if main interfaces are correctly set for every interface type. Each host must either have only one main
	 * interface for each interface type, or have no interface of that type at all.
	 *
	 * @param array $host_prototype  Host prototype object.
	 * @param array $interfaces      All single host prototype interfaces including existing ones in DB.
	 *
	 * @throws APIException  if two main or no main interfaces are given.
	 */
	private static function checkMainInterfaces(array $host_prototypes): void {
		foreach ($host_prototypes as $host_prototype) {
			if ($host_prototype['custom_interfaces'] != HOST_PROT_INTERFACES_CUSTOM) {
				continue;
			}

			$interface_types = [];

			foreach ($host_prototype['interfaces'] as $interface) {
				if (!array_key_exists($interface['type'], $interface_types)) {
					$interface_types[$interface['type']] = [INTERFACE_PRIMARY => 0, INTERFACE_SECONDARY => 0];
				}

				$interface_types[$interface['type']][$interface['main']]++;

				if ($interface_types[$interface['type']][INTERFACE_PRIMARY] > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Host prototype cannot have more than one default interface of the same type.')
					);
				}
			}

			foreach ($interface_types as $type => $counters) {
				if ($counters[INTERFACE_SECONDARY] > 0 && $counters[INTERFACE_PRIMARY] == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No default interface for "%1$s" type on "%2$s".',
						hostInterfaceTypeNumToName($type), $host_prototype['name']
					));
				}
			}
		}
	}

	/**
	 * Deletes the given group prototype and all discovered groups.
	 * Deletes also group prototype children.
	 *
	 * @param array $group_prototypeids
	 */
	private static function deleteGroupPrototypes(array $group_prototypeids): void {
		// Lock group prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionId('gp.group_prototypeid', $group_prototypeids).
			' FOR UPDATE'
		);

		$child_group_prototypeids = DB::select('group_prototype', [
			'output' => [],
			'filter' => ['templateid' => $group_prototypeids],
			'preservekeys' => true
		]);
		if ($child_group_prototypeids) {
			self::deleteGroupPrototypes(array_keys($child_group_prototypeids));
		}

		$host_groups = DB::select('group_discovery', [
			'output' => ['groupid'],
			'filter' => ['parent_group_prototypeid' => $group_prototypeids]
		]);
		if ($host_groups) {
			API::HostGroup()->delete(array_column($host_groups, 'groupid'), true);
		}

		// delete group prototypes
		DB::delete('group_prototype', ['group_prototypeid' => $group_prototypeids]);
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateInterfaces(array &$host_prototypes, array $db_host_prototypes = null): void {
		$ins_interfaces = [];
		$upd_interfaces = [];
		$del_interfaceids = [];

		foreach ($host_prototypes as &$host_prototype) {
			$db_interfaces = ($db_host_prototypes !== null
					&& array_key_exists('interfaces', $db_host_prototypes[$host_prototype['hostid']]))
				? $db_host_prototypes[$host_prototype['hostid']]['interfaces']
				: [];

			if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_INHERIT) {
				$host_prototype['interfaces'] = [];
			}

			if (!array_key_exists('interfaces', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['interfaces'] as &$interface) {
				$index = self::compareInterface($interface, $db_interfaces);
				if ($index != -1) {
					$interface['interfaceid'] = $db_interfaces[$index]['interfaceid'];

					$upd_interface = DB::getUpdatedValues('interface', $interface, $db_interfaces[$index]);

					if ($upd_interface) {
						$upd_interfaces[] = [
							'values' => $upd_interface,
							'where' => ['interfaceid' => $interface['interfaceid']]
						];
					}

					unset($db_interfaces[$index]);
				}
				else {
					$ins_interfaces[] = ['hostid' => $host_prototype['hostid']] + $interface;
				}
			}
			unset($interface);

			$del_interfaceids = array_merge($del_interfaceids, array_keys($db_interfaces));
		}
		unset($host_prototype);

		if ($del_interfaceids) {
			DB::delete('interface_snmp', ['interfaceid' => $del_interfaceids]);
			DB::delete('interface', ['interfaceid' => $del_interfaceids]);
		}

		if ($upd_interfaces) {
			DB::update('interface', $upd_interfaces);
		}

		if ($ins_interfaces) {
			$snmp_interfaces = [];
			$interfaceids = DB::insert('interface', $ins_interfaces);

			foreach ($host_prototypes as &$host_prototype) {
				if (!array_key_exists('interfaces', $host_prototype)) {
					continue;
				}

				foreach ($host_prototype['interfaces'] as &$interface) {
					if (!array_key_exists('interfaceid', $interface)) {
						$interface['interfaceid'] = array_shift($interfaceids);

						if ($interface['type'] == INTERFACE_TYPE_SNMP) {
							$snmp_interfaces[] = ['interfaceid' => $interface['interfaceid']] + $interface['details'];
						}
					}
				}
				unset($interface);
			}
			unset($host_prototype);

			if ($snmp_interfaces) {
				DB::insert('interface_snmp', $snmp_interfaces, false);
			}
		}
	}

	/**
	 * Compare two interface. Return interface index if they are same, return -1 otherwise.
	 *
	 * @param array $host_interface
	 * @param array $db_interfaces
	 *
	 * @return int
	 */
	private static function compareInterface(array $host_interface, array $db_interfaces): int {
		$interface_fields = ['type', 'ip', 'dns', 'port'];

		foreach ($db_interfaces as $index => $db_interface) {
			foreach ($interface_fields as $field) {
				if (array_key_exists($field, $host_interface) && $host_interface[$field] != $db_interface[$field]) {
					continue 2;
				}
			}

			if ($host_interface['type'] == INTERFACE_TYPE_SNMP) {
				if ($host_interface['details']['version'] != $db_interface['details']['version']) {
					continue;
				}

				if ($host_interface['details']['version'] == SNMP_V1
						|| $host_interface['details']['version'] == SNMP_V2C) {
					foreach (['community', 'bulk'] as $field) {
						if (array_key_exists($field, $host_interface['details'])
								&& $host_interface['details'][$field] != $db_interface['details'][$field]) {
							continue 2;
						}
					}
				}
				elseif ($host_interface['details']['version'] == SNMP_V3) {
					foreach (['bulk', 'securityname', 'securitylevel', 'contextname'] as $field) {
						if (array_key_exists($field, $host_interface['details'])
								&& $host_interface['details'][$field] != $db_interface['details'][$field]) {
							continue 2;
						}
					}

					if (array_key_exists('securitylevel', $host_interface['details'])) {
						if ($host_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
							foreach (['authprotocol', 'authpassphrase'] as $field) {
								if (array_key_exists($field, $host_interface['details'])
										&& $host_interface['details'][$field] != $db_interface['details'][$field]) {
									continue 2;
								}
							}
						}
						elseif ($host_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
							foreach (['authprotocol', 'authpassphrase', 'privprotocol', 'privpassphrase'] as $field) {
								if (array_key_exists($field, $host_interface['details'])
										&& $host_interface['details'][$field] != $db_interface['details'][$field]) {
									continue 2;
								}
							}
						}
					}
				}
			}

			return $index;
		}

		return -1;
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateGroupLinks(array &$host_prototypes, array $db_host_prototypes = null): void {
		$ins_group_links = [];
		$upd_group_links = [];
		$del_group_prototypeids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			$db_group_links = ($db_host_prototypes !== null)
				? array_column($db_host_prototypes[$host_prototype['hostid']]['groupLinks'], null, 'groupid')
				: [];

			foreach ($host_prototype['groupLinks'] as &$group_link) {
				if (array_key_exists($group_link['groupid'], $db_group_links)) {
					$group_link['group_prototypeid'] = $db_group_links[$group_link['groupid']]['group_prototypeid'];

					$upd_group_link = DB::getUpdatedValues('group_prototype', $group_link,
						$db_group_links[$group_link['groupid']]
					);

					if ($upd_group_link) {
						$upd_group_links[] = [
							'values' => $upd_group_link,
							'where' => ['group_prototypeid' => $group_link['group_prototypeid']]
						];
					}

					unset($db_group_links[$group_link['groupid']]);
				}
				else {
					$ins_group_links[] = ['hostid' => $host_prototype['hostid']] + $group_link;
				}
			}
			unset($group_link);

			$del_group_prototypeids = array_merge($del_group_prototypeids,
				array_column($db_group_links, 'group_prototypeid')
			);
		}
		unset($host_prototype);

		if ($ins_group_links) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_links);
		}

		if ($upd_group_links) {
			DB::update('group_prototype', $upd_group_links);
		}

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
		}

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupLinks', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['groupLinks'] as &$group_link) {
				if (!array_key_exists('group_prototypeid', $group_link)) {
					$group_link['group_prototypeid'] = array_shift($group_prototypeids);
				}
			}
			unset($group_link);
		}
		unset($host_prototype);
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateGroupPrototypes(array &$host_prototypes, array $db_host_prototypes = null): void {
		$ins_group_prototypes = [];
		$upd_group_prototypes = [];
		$del_group_prototypeids = [];

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupPrototypes', $host_prototype)) {
				continue;
			}

			$db_group_prototypes = ($db_host_prototypes !== null)
				? array_column($db_host_prototypes[$host_prototype['hostid']]['groupPrototypes'], null, 'name')
				: [];

			foreach ($host_prototype['groupPrototypes'] as &$group_prototype) {
				if (array_key_exists($group_prototype['name'], $db_group_prototypes)) {
					$group_prototype['group_prototypeid']
						= $db_group_prototypes[$group_prototype['name']]['group_prototypeid'];

					$upd_group_prototype = DB::getUpdatedValues('group_prototype', $group_prototype,
						$db_group_prototypes[$group_prototype['name']]
					);

					if ($upd_group_prototype) {
						$upd_group_prototypes[] = [
							'values' => $upd_group_prototype,
							'where' => ['group_prototypeid' => $upd_group_prototype['group_prototypeid']]
						];
					}

					unset($db_group_prototypes[$group_prototype['name']]);
				}
				else {
					$ins_group_prototypes[] = ['hostid' => $host_prototype['hostid']] + $group_prototype;
				}
			}
			unset($group_prototype);

			$del_group_prototypeids = array_merge($del_group_prototypeids,
				array_column($db_group_prototypes, 'group_prototypeid')
			);
		}
		unset($host_prototype);

		if ($ins_group_prototypes) {
			$group_prototypeids = DB::insert('group_prototype', $ins_group_prototypes);
		}

		if ($upd_group_prototypes) {
			DB::update('group_prototype', $upd_group_prototypes);
		}

		if ($del_group_prototypeids) {
			self::deleteGroupPrototypes($del_group_prototypeids);
		}

		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('groupPrototypes', $host_prototype)) {
				continue;
			}

			foreach ($host_prototype['groupPrototypes'] as &$group_prototype) {
				if (!array_key_exists('group_prototypeid', $group_prototype)) {
					$group_prototype['group_prototypeid'] = array_shift($group_prototypeids);
				}
			}
			unset($group_prototype);
		}
		unset($host_prototype);
	}

	/**
	 * @param array      $host_prototypes
	 * @param array|null $db_host_prototypes
	 */
	private static function updateHostInventories(array $host_prototypes, array $db_host_prototypes = null): void {
		$ins_inventories = [];
		$upd_inventories = [];
		$del_hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (!array_key_exists('inventory_mode', $host_prototype)) {
				continue;
			}

			$db_inventory_mode = ($db_host_prototypes !== null)
				? $db_host_prototypes[$host_prototype['hostid']]['inventory_mode']
				: HOST_INVENTORY_DISABLED;

			if ($host_prototype['inventory_mode'] == $db_inventory_mode) {
				continue;
			}

			if ($host_prototype['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$del_hostids[] = $host_prototype['hostid'];
			}
			elseif ($db_inventory_mode != HOST_INVENTORY_DISABLED) {
				$upd_inventories = [
					'values' =>['inventory_mode' => $host_prototype['inventory_mode']],
					'where' => ['hostid' => $host_prototype['hostid']]
				];
			}
			else {
				$ins_inventories[] = [
					'hostid' => $host_prototype['hostid'],
					'inventory_mode' => $host_prototype['inventory_mode']
				];
			}
		}

		if ($ins_inventories) {
			DB::insertBatch('host_inventory', $ins_inventories, false);
		}

		if ($upd_inventories) {
			DB::update('host_inventory', $upd_inventories);
		}

		if ($del_hostids) {
			DB::delete('host_inventory', ['hostid' => $del_hostids]);
		}
	}

	/**
	 * @param array $host_prototypes
	 */
	private static function createHostDiscoveries(array $host_prototypes): void {
		$host_discoveries = [];

		foreach ($host_prototypes as $host_prototype) {
			$host_discoveries[] = [
				'hostid' => $host_prototype['hostid'],
				'parent_itemid' => $host_prototype['ruleid']
			];
		}

		if ($host_discoveries) {
			DB::insertBatch('host_discovery', $host_discoveries, false);
		}
	}

	/**
	 * @return array
	 */
	private static function getInterfacesValidationRules(): array {
		return [
			'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX])],
			'useip' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
			'ip' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'useip', 'in' => INTERFACE_USE_IP], 'type' => API_IP, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
								['else' => true, 'type' => API_IP, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'ip')]
			]],
			'dns' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'useip', 'in' => INTERFACE_USE_DNS], 'type' => API_DNS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
								['else' => true, 'type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO, 'length' => DB::getFieldLength('interface', 'dns')]
			]],
			'port' =>	['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'length' => DB::getFieldLength('interface', 'port')],
			'main' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [INTERFACE_SECONDARY, INTERFACE_PRIMARY])]
		];
	}

	/**
	 * @return array
	 */
	private static function getInterfacesCreateValidationRules(): array {
		return ['type' => API_MULTIPLE, 'rules' => [
			['if' => ['field' => 'custom_interfaces', 'in' => HOST_PROT_INTERFACES_CUSTOM], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NORMALIZE, 'fields' =>
				self::getInterfacesValidationRules() +
				['details' =>	['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => INTERFACE_TYPE_SNMP], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
						'version' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
						'bulk' =>			['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
						'community' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('interface_snmp', 'community')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'contextname' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'securityname' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'securitylevel' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]), 'default' => DB::getDefault('interface_snmp', 'securitylevel')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'authprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'authpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'privprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
												['else' => true, 'type' => API_UNEXPECTED]
						]],
						'privpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
												['else' => true, 'type' => API_UNEXPECTED]
						]]
					]],
					['else' => true, 'type' => API_OBJECT, 'fields' => []]
				]]]
			],
			['else' => true, 'type' => API_OBJECT, 'fields' => []]
		]];
	}

	/**
	 * @return array
	 */
	private static function getInterfacesUpdateValidationRules(): array {
		return ['type' => API_MULTIPLE, 'rules' => [
			['if' => ['field' => 'custom_interfaces', 'in' => HOST_PROT_INTERFACES_CUSTOM], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NORMALIZE, 'fields' =>
				self::getInterfacesValidationRules() +
				['details' =>	['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'type', 'in' => INTERFACE_TYPE_SNMP], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
						'version' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
						'bulk' =>			['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
						'community' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'community')],
						'contextname' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
						'securityname' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
						'securitylevel' =>	['type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])],
						'authprotocol' =>	['type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
						'authpassphrase' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
						'privprotocol' =>	['type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
						'privpassphrase' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')]
					]],
					['else' => true, 'type' => API_OBJECT, 'fields' => []]
				]]]
			],
			['else' => true, 'type' => API_OBJECT, 'fields' => []]
		]];
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private static function populateInterfaces(array &$host_prototypes, array $db_host_prototypes): void {
		foreach ($host_prototypes as &$host_prototype) {
			if ($host_prototype['custom_interfaces'] != HOST_PROT_INTERFACES_CUSTOM) {
				continue;
			}

			$db_interfaces = $db_host_prototypes[$host_prototype['hostid']]['interfaces'];

			foreach ($host_prototype['interfaces'] as &$interface) {
				$index = self::compareInterface($interface, $db_interfaces);
				if ($index === -1) {
					continue;
				}

				$interface = $interface + $db_interfaces[$index];

				// Remove interfaceid.
				$interface = array_diff_key($interface, array_flip(['interfaceid']));

				if ($interface['type'] == INTERFACE_TYPE_SNMP) {
					$interface['details'] = $interface['details'] + $db_interfaces[$index]['details'];
				}

				unset($db_interfaces[$index]);
			}
			unset($interface);
		}
		unset($host_prototype);
	}

	private static function validateSnmpInterfaces(array $host_prototypes): void {
		foreach ($host_prototypes as $host_index => $host_prototype) {
			if ($host_prototype['custom_interfaces'] != HOST_PROT_INTERFACES_CUSTOM) {
				continue;
			}

			foreach ($host_prototype['interfaces'] as $index => $interface) {
				if ($interface['type'] != INTERFACE_TYPE_SNMP) {
					continue;
				}

				$path = '/'.($host_index + 1).'/interfaces/'.($index + 1).'/details';
				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'version' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SNMP_V1, SNMP_V2C, SNMP_V3])],
					'bulk' =>			['type' => API_INT32, 'in' => implode(',', [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED])],
					'community' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'version', 'in' => implode(',', [SNMP_V1, SNMP_V2C])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('interface_snmp', 'community')],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'contextname' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'contextname')],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'securityname' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'securityname')],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'securitylevel' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'version', 'in' => SNMP_V3], 'type' => API_INT32, 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'authprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3AuthProtocols()))],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'authpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'securitylevel', 'in' => implode(',', [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'authpassphrase')],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'privprotocol' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_INT32, 'in' => implode(',', array_keys(getSnmpV3PrivProtocols()))],
											['else' => true, 'type' => API_UNEXPECTED]
					]],
					'privpassphrase' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'securitylevel', 'in' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('interface_snmp', 'privpassphrase')],
											['else' => true, 'type' => API_UNEXPECTED]
					]]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $interface['details'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
	}

	/**
	 * @param array $host_prototypes
	 * @param bool  $permission
	 *
	 * @return array
	 */
	private function getAffectedObjects(array $host_prototypes, bool $permission = true): array {
		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode',
				'templateid'
			],
			'selectDiscoveryRule' => ['itemid'],
			'hostids' => array_column($host_prototypes, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if ($permission) {
			if (count($host_prototypes) != count($db_host_prototypes)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->addAffectedObjects($host_prototypes, $db_host_prototypes);

		return $db_host_prototypes;
	}

	private static function trimInterfaceDetails(array $details): array {
		if ($details['version'] == SNMP_V3) {
			$details = array_diff_key($details, array_flip(['interfaceid', 'community']));

			if ($details['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				$details = array_diff_key($details, array_flip(['authprotocol', 'authpassphrase', 'privprotocol',
					'privpassphrase'
				]));
			}
			elseif ($details['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
				$details = array_diff_key($details, array_flip(['privprotocol', 'privpassphrase']));
			}
		}
		else {
			$details = array_diff_key($details, array_flip(['interfaceid', 'securityname', 'securitylevel',
				'authpassphrase', 'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'
			]));
		}

		return $details;
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	protected function addAffectedObjects(array $host_prototypes, array &$db_host_prototypes): void {
		foreach ($host_prototypes as $host_prototype) {
			$hostid = $host_prototype['hostid'];

			$db_host_prototypes[$hostid]['ruleid'] = $db_host_prototypes[$hostid]['discoveryRule']['itemid'];
			unset($db_host_prototypes[$hostid]['discoveryRule']);
		}

		$this->addAffectedInterfaces($host_prototypes, $db_host_prototypes);
		$this->addAffectedGroupLinks($host_prototypes, $db_host_prototypes);
		$this->addAffectedGroupPrototypes($host_prototypes, $db_host_prototypes);
		parent::addAffectedObjects($host_prototypes, $db_host_prototypes);
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private function addAffectedInterfaces(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			$hostid = $host_prototype['hostid'];

			if (array_key_exists('interfaces', $host_prototype)
					|| $db_host_prototypes[$hostid]['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				$hostids[] = $hostid;
				$db_host_prototypes[$hostid]['interfaces'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$hostid_by_interfaceid = [];
		$options = [
			'output' => ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port'],
			'filter' => ['hostid' => $hostids]
		];
		$db_interfaces = DBselect(DB::makeSql('interface', $options));

		while ($db_interface = DBfetch($db_interfaces)) {
			$db_host_prototypes[$db_interface['hostid']]['interfaces'][$db_interface['interfaceid']] =
				array_diff_key($db_interface, array_flip(['hostid']));

			if ($db_interface['type'] == INTERFACE_TYPE_SNMP) {
				$hostid_by_interfaceid[$db_interface['interfaceid']] = $db_interface['hostid'];
			}
		}

		if ($hostid_by_interfaceid) {
			$options = [
				'output' => ['interfaceid', 'version', 'bulk', 'community', 'securityname', 'securitylevel',
					'authpassphrase', 'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'
				],
				'filter' => ['interfaceid' => array_keys($hostid_by_interfaceid)]
			];
			$db_snmps = DBselect(DB::makeSql('interface_snmp', $options));

			while ($db_snmp = DBfetch($db_snmps)) {
				$hostid = $hostid_by_interfaceid[$db_snmp['interfaceid']];
				$db_host_prototypes[$hostid]['interfaces'][$db_snmp['interfaceid']]['details'] =
					self::trimInterfaceDetails($db_snmp);
			}
		}
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private function addAffectedGroupLinks(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('groupLinks', $host_prototype)) {
				$hostids[] = $host_prototype['hostid'];
				$db_host_prototypes[$host_prototype['hostid']]['groupLinks'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$options = [
			'output' => ['group_prototypeid', 'hostid', 'groupid', 'templateid'],
			'filter' => ['hostid' => $hostids, 'name' => '']
		];
		$db_links = DBselect(DB::makeSql('group_prototype', $options));

		while ($db_link = DBfetch($db_links)) {
			$db_host_prototypes[$db_link['hostid']]['groupLinks'][$db_link['group_prototypeid']] =
				array_diff_key($db_link, array_flip(['hostid']));
		}
	}

	/**
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	private function addAffectedGroupPrototypes(array $host_prototypes, array &$db_host_prototypes): void {
		$hostids = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('groupPrototypes', $host_prototype)) {
				$hostids[] = $host_prototype['hostid'];
				$db_host_prototypes[$host_prototype['hostid']]['groupPrototypes'] = [];
			}
		}

		if (!$hostids) {
			return;
		}

		$options = [
			'output' => ['group_prototypeid', 'hostid', 'name', 'templateid'],
			'filter' => ['hostid' => $hostids, 'groupid' => '0']
		];
		$db_groups = DBselect(DB::makeSql('group_prototype', $options));

		while ($db_link = DBfetch($db_groups)) {
			$db_host_prototypes[$db_link['hostid']]['groupPrototypes'][$db_link['group_prototypeid']] =
				array_diff_key($db_link, array_flip(['hostid']));
		}
	}
}
