<?php //-->
/**
 * This file is part of a Custom Package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception;

use Cradle\Http\Request;
use Cradle\Http\Response;

/**
 * Creates a activity
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('activity-create', function ($request, $response) {
    //set activity as schema
    $request->setStage('schema', 'activity');

    //trigger model create
    $this->trigger('system-model-create', $request, $response);
});

/**
 * Creates a activity
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('activity-detail', function ($request, $response) {
    //set activity as schema
    $request->setStage('schema', 'activity');

    //trigger model detail
    $this->trigger('system-model-detail', $request, $response);
});

/**
 * Removes a activity
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('activity-remove', function ($request, $response) {
    //set activity as schema
    $request->setStage('schema', 'activity');

    //trigger model remove
    $this->trigger('system-model-remove', $request, $response);
});

/**
 * Restores a activity
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('activity-restore', function ($request, $response) {
    //set activity as schema
    $request->setStage('schema', 'activity');

    //trigger model restore
    $this->trigger('system-model-restore', $request, $response);
});

/**
 * Searches activity
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('activity-search', function ($request, $response) {
    // //set activity as schema
    // $request->setStage('schema', 'activity');
    //
    // $profile = $request->getStage('filter', 'profile_id');
    //
    // $request->removeStage('filter', 'profile_id');
    // // cradle()->inspect($request->getStage());exit;
    //
    // //trigger model search
    // $this->trigger('system-model-search', $request, $response);
    //
    // $results = $response->getResults();cradle()->inspect($results);exit;
    // $activities = [];
    // foreach ($results['rows'] as $key => $activity) {
    //     $activities[$activity['history_id']] = $activity;
    // }
    //
    // $historyRequest = new Request();
    // $historyResponse = new Response();
    //
    // $historyRequest->setStage('schema', 'history');
    // $historyRequest->setStage('in_filter', 'history_id', array_keys($activities));
    // if ($profile) {
    //     $historyRequest->setStage('filter', 'profile_id', $profile);
    // }
    //
    // //trigger model search
    // $this->trigger('system-model-search', $historyRequest, $historyResponse);
    // $histories = $historyResponse->getResults('rows');
    //
    // foreach ($histories as $key => $history) {
    //     $activities[$history['history_id']] =  array_merge($activities[$history['history_id']], $history);
    // }
    //
    // $results['rows'] = array_values($activities);
    //
    // $response->setResults($results);
    $data = [];
    if($request->hasStage()) {
        $data = $request->getStage();
    }

    $range = 50;
    $start = 0;

    if (isset($data['range']) && is_numeric($data['range'])) {
        $range = $data['range'];
    }

    if (isset($data['start']) && is_numeric($data['start'])) {
        $start = $data['start'];
    }

    $schema = Schema::i('activity');

    $sum = null;
    $filter = [];
    $in = [];
    $span  = [];
    $range = 50;
    $start = 0;
    $order = [];
    $count = 0;

    if (isset($data['filter']) && is_array($data['filter'])) {
        $filter = $data['filter'];
    }

    if (isset($data['span']) && is_array($data['span'])) {
        $span = $data['span'];
    }

    if (isset($data['in_filter']) && is_array($data['in_filter'])) {
        $in = $data['in_filter'];
    }

    if (isset($data['range']) && is_numeric($data['range'])) {
        $range = $data['range'];
    }

    if (isset($data['start']) && is_numeric($data['start'])) {
        $start = $data['start'];
    }

    if (isset($data['order']) && is_array($data['order'])) {
        $order = $data['order'];
    }

    if (isset($data['sum']) && !empty($data['sum'])) {
        $sum = sprintf('sum(%s) as total', $data['sum']);
    }

    $active = $schema->getActiveFieldName();
    if ($active && !isset($filter[$active])) {
        $filter[$active] = 1;
    }

    $search = $schema->model()->service('sql')->getResource()
        ->search($schema->getName())
        ->setStart($start)
        ->setRange($range)
        ->innerJoinUsing('activity_history', 'activity_id')
        ->innerJoinUsing('history', 'history_id')
        ->innerJoinUsing('history_profile', 'history_id')
        ->innerJoinUsing('profile', 'profile_id');

    //add filters
    foreach ($filter as $column => $value) {
        if (preg_match('/^[a-zA-Z0-9-_]+$/', $column)) {
            $search->addFilter($column . ' = %s', $value);
        }
    }

    // add in filters
    foreach ($in as $column => $values) {
        if (preg_match('/^[a-zA-Z0-9-_]+$/', $column)) {
            $search->addFilter($column . ' IN ("' . implode('", "', $values) . '")');
        }
    }

    //add spans
    foreach ($span as $column => $value) {
        if (!empty($value)) {
            if (!preg_match('/^[a-zA-Z0-9-_]+$/', $column)) {
                continue;
            }

            // minimum?
            if (isset($value[0]) && !empty($value[0])) {
                $search
                    ->addFilter($column . ' >= %s', $value[0]);
            }

            // maximum?
            if (isset($value[1]) && !empty($value[0])) {
                $search
                    ->addFilter($column . ' <= %s', $value[1]);
            }
        }
    }

    //keyword?
    $searchable = $schema->getSearchableFieldNames();

    if (!empty($searchable)) {
        $keywords = [];

        if (isset($data['q'])) {
            $keywords = $data['q'];

            if (!is_array($keywords)) {
                $keywords = [$keywords];
            }
        }

        foreach ($keywords as $keyword) {
            $or = [];
            $where = [];
            foreach ($searchable as $name) {
                $where[] = 'LOWER(' . $name . ') LIKE %s';
                $or[] = '%' . strtolower($keyword) . '%';
            }

            array_unshift($or, '(' . implode(' OR ', $where) . ')');
            call_user_func([$search, 'addFilter'], ...$or);
        }
    }

    //add sorting
    foreach ($order as $sort => $direction) {
        $search->addSort($sort, $direction);
    }

    $rows = $search->getRows();
    $fields = $schema->getJsonFieldNames();

    foreach ($rows as $i => $results) {
        foreach ($fields as $field) {
            if (isset($results[$field]) && $results[$field]) {
                $rows[$i][$field] = json_decode($results[$field], true);
            } else {
                $rows[$i][$field] = [];
            }
        }
    }

    //return response format
    $results =  [
        'rows' => $rows,
        'total' => $search->getTotal()
    ];

    if ($sum) {
        $total = $search
            ->setColumns($sum)
            ->getRow();

        $results['sum_field'] = $total['total'] ? $total['total'] : 0;
    }

    $response->setResults($results);
});

/**
 * Updates a activity
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('activity-update', function ($request, $response) {
    //set activity as schema
    $request->setStage('schema', 'activity');

    //trigger model update
    $this->trigger('system-model-update', $request, $response);
});
