import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/rotations',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;
/**
 * @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/rotations',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\Admin\RotationController::updateStatus
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
export const updateStatus = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateStatus.url(args, options),
    method: 'patch',
});

updateStatus.definition = {
    methods: ['patch'],
    url: '/admin/rotations/{rotation}/status',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateStatus
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
updateStatus.url = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rotation: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { rotation: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            rotation: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        rotation:
            typeof args.rotation === 'object'
                ? args.rotation.id
                : args.rotation,
    };

    return (
        updateStatus.definition.url
            .replace('{rotation}', parsedArgs.rotation.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateStatus
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
updateStatus.patch = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateStatus.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateStatus
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
const updateStatusForm = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateStatus.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateStatus
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
updateStatusForm.patch = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateStatus.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

updateStatus.form = updateStatusForm;
/**
 * @see \App\Http\Controllers\Admin\RotationController::storeAssignment
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
export const storeAssignment = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeAssignment.url(args, options),
    method: 'post',
});

storeAssignment.definition = {
    methods: ['post'],
    url: '/admin/rotations/{rotation}/assignments',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\RotationController::storeAssignment
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
storeAssignment.url = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rotation: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { rotation: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            rotation: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        rotation:
            typeof args.rotation === 'object'
                ? args.rotation.id
                : args.rotation,
    };

    return (
        storeAssignment.definition.url
            .replace('{rotation}', parsedArgs.rotation.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\RotationController::storeAssignment
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
storeAssignment.post = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeAssignment.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::storeAssignment
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
const storeAssignmentForm = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeAssignment.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::storeAssignment
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
storeAssignmentForm.post = (
    args:
        | { rotation: string | { id: string } }
        | [rotation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeAssignment.url(args, options),
    method: 'post',
});

storeAssignment.form = storeAssignmentForm;
/**
 * @see \App\Http\Controllers\Admin\RotationController::updateAssignmentStatus
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
export const updateAssignmentStatus = (
    args:
        | { rotationAssignment: string | { id: string } }
        | [rotationAssignment: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateAssignmentStatus.url(args, options),
    method: 'patch',
});

updateAssignmentStatus.definition = {
    methods: ['patch'],
    url: '/admin/rotation-assignments/{rotationAssignment}/status',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateAssignmentStatus
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
updateAssignmentStatus.url = (
    args:
        | { rotationAssignment: string | { id: string } }
        | [rotationAssignment: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rotationAssignment: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { rotationAssignment: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            rotationAssignment: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        rotationAssignment:
            typeof args.rotationAssignment === 'object'
                ? args.rotationAssignment.id
                : args.rotationAssignment,
    };

    return (
        updateAssignmentStatus.definition.url
            .replace(
                '{rotationAssignment}',
                parsedArgs.rotationAssignment.toString(),
            )
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateAssignmentStatus
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
updateAssignmentStatus.patch = (
    args:
        | { rotationAssignment: string | { id: string } }
        | [rotationAssignment: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateAssignmentStatus.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateAssignmentStatus
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
const updateAssignmentStatusForm = (
    args:
        | { rotationAssignment: string | { id: string } }
        | [rotationAssignment: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateAssignmentStatus.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\RotationController::updateAssignmentStatus
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
updateAssignmentStatusForm.patch = (
    args:
        | { rotationAssignment: string | { id: string } }
        | [rotationAssignment: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateAssignmentStatus.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

updateAssignmentStatus.form = updateAssignmentStatusForm;
const RotationController = {
    index,
    store,
    updateStatus,
    storeAssignment,
    updateAssignmentStatus,
};

export default RotationController;
