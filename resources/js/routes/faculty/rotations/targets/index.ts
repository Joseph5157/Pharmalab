import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::store
* @see app/Http/Controllers/Faculty/RotationTargetController.php:58
* @route '/faculty/rotations/{rotation}/targets'
*/
export const store = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/faculty/rotations/{rotation}/targets',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::store
* @see app/Http/Controllers/Faculty/RotationTargetController.php:58
* @route '/faculty/rotations/{rotation}/targets'
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
* @see \App\Http\Controllers\Faculty\RotationTargetController::store
* @see app/Http/Controllers/Faculty/RotationTargetController.php:58
* @route '/faculty/rotations/{rotation}/targets'
*/
store.post = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::store
* @see app/Http/Controllers/Faculty/RotationTargetController.php:58
* @route '/faculty/rotations/{rotation}/targets'
*/
const storeForm = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::store
* @see app/Http/Controllers/Faculty/RotationTargetController.php:58
* @route '/faculty/rotations/{rotation}/targets'
*/
storeForm.post = (args: { rotation: string | { id: string } } | [rotation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::update
* @see app/Http/Controllers/Faculty/RotationTargetController.php:80
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
export const update = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/faculty/rotations/{rotation}/targets/{target}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::update
* @see app/Http/Controllers/Faculty/RotationTargetController.php:80
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
update.url = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            rotation: args[0],
            target: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        rotation: typeof args.rotation === 'object'
        ? args.rotation.id
        : args.rotation,
        target: typeof args.target === 'object'
        ? args.target.id
        : args.target,
    }

    return update.definition.url
            .replace('{rotation}', parsedArgs.rotation.toString())
            .replace('{target}', parsedArgs.target.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::update
* @see app/Http/Controllers/Faculty/RotationTargetController.php:80
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
update.patch = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::update
* @see app/Http/Controllers/Faculty/RotationTargetController.php:80
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
const updateForm = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::update
* @see app/Http/Controllers/Faculty/RotationTargetController.php:80
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
updateForm.patch = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::destroy
* @see app/Http/Controllers/Faculty/RotationTargetController.php:97
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
export const destroy = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/faculty/rotations/{rotation}/targets/{target}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::destroy
* @see app/Http/Controllers/Faculty/RotationTargetController.php:97
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
destroy.url = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            rotation: args[0],
            target: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        rotation: typeof args.rotation === 'object'
        ? args.rotation.id
        : args.rotation,
        target: typeof args.target === 'object'
        ? args.target.id
        : args.target,
    }

    return destroy.definition.url
            .replace('{rotation}', parsedArgs.rotation.toString())
            .replace('{target}', parsedArgs.target.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::destroy
* @see app/Http/Controllers/Faculty/RotationTargetController.php:97
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
destroy.delete = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::destroy
* @see app/Http/Controllers/Faculty/RotationTargetController.php:97
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
const destroyForm = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Faculty\RotationTargetController::destroy
* @see app/Http/Controllers/Faculty/RotationTargetController.php:97
* @route '/faculty/rotations/{rotation}/targets/{target}'
*/
destroyForm.delete = (args: { rotation: string | { id: string }, target: string | { id: string } } | [rotation: string | { id: string }, target: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const targets = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default targets