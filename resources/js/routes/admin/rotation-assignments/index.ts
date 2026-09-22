import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
export const store = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/rotations/{rotation}/assignments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
store.url = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rotation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { rotation: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    rotation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rotation: typeof args.rotation === 'object'
                ? args.rotation.id
                : args.rotation,
                }

    return store.definition.url
            .replace('{rotation}', parsedArgs.rotation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
store.post = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
    const storeForm = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:91
 * @route '/admin/rotations/{rotation}/assignments'
 */
        storeForm.post = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
export const status = (args: { rotationAssignment: string | { id: string } } | [rotationAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
})

status.definition = {
    methods: ["patch"],
    url: '/admin/rotation-assignments/{rotationAssignment}/status',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
status.url = (args: { rotationAssignment: string | { id: string } } | [rotationAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rotationAssignment: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { rotationAssignment: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    rotationAssignment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rotationAssignment: typeof args.rotationAssignment === 'object'
                ? args.rotationAssignment.id
                : args.rotationAssignment,
                }

    return status.definition.url
            .replace('{rotationAssignment}', parsedArgs.rotationAssignment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
status.patch = (args: { rotationAssignment: string | { id: string } } | [rotationAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
    const statusForm = (args: { rotationAssignment: string | { id: string } } | [rotationAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: status.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:116
 * @route '/admin/rotation-assignments/{rotationAssignment}/status'
 */
        statusForm.patch = (args: { rotationAssignment: string | { id: string } } | [rotationAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: status.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    status.form = statusForm
const rotationAssignments = {
    store: Object.assign(store, store),
status: Object.assign(status, status),
}

export default rotationAssignments