<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;

use Cradle\Http\Request;
use Cradle\Http\Response;


/**
 * Render the Activity Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/:schema/activity', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $data = $request->getStage();
    // set redirect
    $redirect = sprintf(
        '/admin/system/model/%s/search',
        $request->getStage('schema')
    );

    if ($request->getStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', $redirect);
    //----------------------------//
    // 2. Validate
    // does the schema exists?
    try {
        $schema = Schema::i($request->getStage('schema'));
        $data['schema'] = $schema->getAll();
    } catch (\Exception $e) {
        $message = $this
            ->package('global')
            ->translate($e->getMessage());

        $redirect = '/admin/system/schema/search';
        $this->package('global')->flash($message, 'error');

        $this->package('global')->redirect($redirect);
    }

    $schemas = [];
    $detailedSchemas = [];
    if ($schema->getReverseRelations()) {
        $relations = $schema->getReverseRelations();
        $relationNames = array_keys($relations);

        foreach ($relationNames as $key => $relation) {
            $relationNames[$key] = str_replace('_' . $request->getStage('schema'), '', $relation);
        }

        // are there any specified schema?
        if (isset($data['timeline']) && $data['timeline']) {
            $schemas = explode(',', $data['timeline']);
            foreach ($schemas as $key => $value) {
                $name = $value;

                if (!strpos($value, '[') && !in_array($name, $relationNames)) {
                    unset($schemas[$key]);
                    continue;
                }

                if (strpos($value, '[') !== FALSE) {
                    $start = strpos($value, '[');
                    $name = substr($value, 0, $start);

                    if (!in_array($name, $relationNames) && $name != $request->getStage('schema')) {
                        unset($schemas[$key]);
                        continue;
                    }

                    $detailedSchemas[$name] = [];
                    $schemas[$name] = [
                        'detail' => substr($value, $start + 1, -1)
                    ];
                } else {
                    $schemas[$name] = [];
                }

                try {
                    $schema = Schema::i($name)->getAll();
                    $schemas[$name]['primary'] = $schema['primary'];
                    $schemas[$name]['icon'] = $schema['icon'];
                    $schemas[$name]['ids'] = [];
                } catch (\Exception $e) {
                }

                unset($schemas[$key]);
            }
        // if no specified schema to display, we will be displaying all
        // that is related to this schema
        } else {
            $schemas = $relationNames;

            foreach ($schemas as $key => $name) {
                try {
                    $schema = Schema::i($name)->getAll();
                    $schemas[$name]['primary'] = $schema['primary'];
                    $schemas[$name]['icon'] = $schema['icon'];
                    $schemas[$name]['ids'] = [];
                } catch (\Exception $e) {
                }

                unset($schemas[$key]);
            }
        }
    }

    // if not yet added,
    // we have add this schema to pool of activity we will be pulling
    if (!isset($schemas[$request->getStage('schema')])) {
        $schemas[$request->getStage('schema')] = [
            'primary' => $data['schema']['primary']
        ];

        if ($data['schema']['icon']) {
            $schemas[$request->getStage('schema')]['icon'] = $data['schema']['icon'];
        }
    }

    $data['schemas'] = $schemas;

    $suggestion = $data['schema']['suggestion'];
    $handlebars = cradle('global')->handlebars();
    $compiled = $handlebars->compile($data['schema']['suggestion']);

    $request->setStage('order', ['activity_created' => 'DESC']);
    $request->setStage('in_filter', 'activity_schema', array_keys($schemas));
    $this->trigger('activity-search', $request, $response);

    $data['history'] = $response->getResults('rows');

    // get ids of detailed schemas
    foreach ($data['history'] as $key => $value) {
        $schemas[$value['activity_schema']]['ids'][] = $value['activity_schema_primary'];
    }

    $details = [];
    foreach ($schemas as $schema => $detail) {
        $detailRequest = new Request();
        $detailResponse = new Response();

        $detailRequest->setStage(
            'in_filter',
            $detail['primary'],
            $detail['ids']
        );

        $detailRequest->setStage('schema', $schema);

        $activityFilter = [];

        if ($request->getStage('activity_filter')) {
            $activityFilter = $request->getStage('activity_filter');
        }

        foreach ($activityFilter as $key => $filter) {
            $detailRequest->setStage('filter', $key, $filter);
        }

        $this->trigger('system-model-search', $detailRequest, $detailResponse);
        $details[$schema] = $detailResponse->getResults('rows');
    }

    foreach ($details as $schema => $items) {
        foreach ($items as $key => $value) {
            if (isset($schemas[$schema]['detail']) && isset($value[$schemas[$schema]['detail']])) {
                $value['detail'] = $value[$schemas[$schema]['detail']];
            }

            $value = array_merge($value, [
                'name' => $compiled($value),
                'image' => isset($value[$request->getStage('schema').'_image']) ?
                    $value[$request->getStage('schema').'_image'] : null
            ]);

            $details[$schema][$value[$schemas[$schema]['primary']]] = $value;
        }
    }

    foreach ($data['history'] as $key => $history) {
        $schema = $history['activity_schema'];
        if (!isset($details[$schema][$history['activity_schema_primary']])) {
            unset($data['history'][$key]);
            continue;
        }

        $data['history'][$key] = array_merge(
            $history,
            $details[$schema][$history['activity_schema_primary']]
        );
    }

    $data['details'] = $details;

    if (isset($data['create']) && strpos($data['create'], '[')) {
        $start = strpos($data['create'], '[');
        $data['create'] = [
            'schema' => substr($data['create'], 0, $start),
            'name' => substr($data['create'], $start+1, -1)
        ];
    }

    // if this is a return back from processing
    // this form and it's because of an error
    if ($response->isError()) {
        //pass the error messages to the template
        $this
            ->package('global')
            ->flash($response->getMessage(), 'error');
        $this
            ->package('global')
            ->redirect($redirect);
    }

    //----------------------------//
    // 3. Render Template
    $data['title'] = $this
        ->package('global')
        ->translate('%s Activity', $data['schema']['singular']);

    // this is just a flag for links
    $data['admin'] =  true;

    $class = sprintf(
            'page-admin-%s-activity page-admin-activity page-admin',
            $request->getStage('schema')
        );
    $body = $this
        ->package('cradlephp/cradle-activity')
        ->template(
            'timeline',
            $data,
            [],
            $response->getPage('template_root'),
            $response->getPage('partials_root')
        );

    // Set Content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    // Render blank page
    $this->trigger('admin-render-page', $request, $response);
});


/**
 * Render the Activity Page of specific schema id
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/:schema/:schema_id/activity', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $data = $request->getStage();
    // set redirect
    $redirect = sprintf(
        '/admin/system/model/%s/search',
        $request->getStage('schema')
    );

    if ($request->getStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', $redirect);
    //----------------------------//
    // 2. Validate
    // does the schema exists?
    try {
        $schema = Schema::i($request->getStage('schema'));
        $data['schema'] = $schema->getAll();
    } catch (\Exception $e) {
        $message = $this
            ->package('global')
            ->translate($e->getMessage());

        $redirect = '/admin/system/schema/search';
        $this->package('global')->flash($message, 'error');
        $this->package('global')->redirect($redirect);
    }

    $filterType = 'activity_filter';
    if ($request->getStage('schema') == 'profile') {
        $filterType = 'filter';
    }

    $request->setStage(
        $filterType,
        $data['schema']['primary'],
        $request->getStage('schema_id')
    );

    //now let the object create take over
    $this->routeTo(
        'get',
        sprintf(
            '/admin/%s/activity',
            $request->getStage('schema')
        ),
        $request,
        $response
    );
});

/**
 * Process the form submit in Activity Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->post('/admin/:schema/activity', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = $request->getStage();

    //----------------------------//
    // 2. Validate
    // does the schema exists?
    try {
        $schema = Schema::i($data['schema'])->getAll();
    } catch (\Exception $e) {
        $message = $this
            ->package('global')
            ->translate($e->getMessage());

        $redirect = '/admin/system/schema/search';
        $this->package('global')->flash($message, 'error');
        $this->package('global')->redirect($redirect);
    }

    //----------------------------//
    // 3. Prepare Data
    $stage = $request->getStage();

    if ($request->getSession('me')) {
        $stage =  array_merge($request->getSession('me'), $stage);
    }

    if (isset($stage['schema_id'])) {
        $stage[$schema['primary']] = $stage['schema_id'];
    }

    $request->setStage($stage);

    //----------------------------//
    // 4. Process
    $this->routeTo(
        'post',
        sprintf(
            '/admin/system/model/%s/create',
            $request->getStage('schema')
        ),
        $request,
        $response
    );
});
