import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/rotations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\RotationController::index
 * @see app/Http/Controllers/Admin/RotationController.php:30
 * @route '/admin/rotations'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
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
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/rotations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\RotationController::store
 * @see app/Http/Controllers/Admin/RotationController.php:47
 * @route '/admin/rotations'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
export const status = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
})

status.definition = {
    methods: ["patch"],
    url: '/admin/rotations/{rotation}/status',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
status.url = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return status.definition.url
            .replace('{rotation}', parsedArgs.rotation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
status.patch = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\RotationController::status
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
    const statusForm = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Admin/RotationController.php:77
 * @route '/admin/rotations/{rotation}/status'
 */
        statusForm.patch = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: status.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    status.form = statusForm
const rotations = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
status: Object.assign(status, status),
}

export default rotations