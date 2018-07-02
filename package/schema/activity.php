<?php //-->
return array (
  'redirect_uri' => '/admin/system/schema/search',
  'singular' => 'Activity',
  'plural' => 'Activities',
  'name' => 'activity',
  'icon' => 'far fa-futbol',
  'detail' => 'Collection of Activities',
  'fields' =>
    [
        [
          'label' => 'Schema',
          'name' => 'schema',
          'field' =>
          [
            'type' => 'text',
          ],
          'list' =>
          [
            'format' => 'none',
          ],
          'detail' =>
          [
            'format' => 'none',
          ],
          'default' => '',
          'searchable' => '1',
          'disable' => '1',
        ],
        [
          'label' => 'Schema Primary',
          'name' => 'schema_primary',
          'field' =>
          [
            'type' => 'number',
          ],
          'list' =>
          [
            'format' => 'none',
          ],
          'detail' =>
          array (
            'format' => 'none',
          ),
          'default' => '',
          'searchable' => '1',
          'disable' => '1',
        ],
        [
            'disable' => '1',
            'label' => 'Created',
            'name' => 'created',
            'field' => [
                'type' => 'created',
            ],
            'list' => [
                'format' => 'none',
            ],
            'detail' => [
                'format' => 'none',
            ],
            'default' => 'NOW()',
            'sortable' => '1'
        ],
        [
            'disable' => '1',
            'label' => 'Updated',
            'name' => 'updated',
            'field' => [
                'type' => 'updated',
            ],
            'list' => [
                'format' => 'none',
            ],
            'detail' => [
                'format' => 'none',
            ],
            'default' => 'NOW()',
            'sortable' => '1'
        ]
    ],
  'relations' => [
    [
        'many' => '1',
        'name' => 'history'
    ]
  ],
  'suggestion' => '{{activity_schema}}',
  'disable' => '1',
);
