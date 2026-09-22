import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/student/sync-spike',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CaseDraftNoteController::index
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
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
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
export const show = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/student/sync-spike/{caseDraftNote}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
show.url = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { caseDraftNote: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { caseDraftNote: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    caseDraftNote: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        caseDraftNote: typeof args.caseDraftNote === 'object'
                ? args.caseDraftNote.id
                : args.caseDraftNote,
                }

    return show.definition.url
            .replace('{caseDraftNote}', parsedArgs.caseDraftNote.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
show.get = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
show.head = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
    const showForm = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
        showForm.get = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CaseDraftNoteController::show
 * @see app/Http/Controllers/CaseDraftNoteController.php:35
 * @route '/student/sync-spike/{caseDraftNote}'
 */
        showForm.head = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\CaseDraftNoteController::sync
 * @see app/Http/Controllers/CaseDraftNoteController.php:44
 * @route '/student/sync-spike/{caseDraftNote}'
 */
export const sync = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/sync-spike/{caseDraftNote}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\CaseDraftNoteController::sync
 * @see app/Http/Controllers/CaseDraftNoteController.php:44
 * @route '/student/sync-spike/{caseDraftNote}'
 */
sync.url = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { caseDraftNote: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { caseDraftNote: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    caseDraftNote: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        caseDraftNote: typeof args.caseDraftNote === 'object'
                ? args.caseDraftNote.id
                : args.caseDraftNote,
                }

    return sync.definition.url
            .replace('{caseDraftNote}', parsedArgs.caseDraftNote.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CaseDraftNoteController::sync
 * @see app/Http/Controllers/CaseDraftNoteController.php:44
 * @route '/student/sync-spike/{caseDraftNote}'
 */
sync.put = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\CaseDraftNoteController::sync
 * @see app/Http/Controllers/CaseDraftNoteController.php:44
 * @route '/student/sync-spike/{caseDraftNote}'
 */
    const syncForm = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sync.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CaseDraftNoteController::sync
 * @see app/Http/Controllers/CaseDraftNoteController.php:44
 * @route '/student/sync-spike/{caseDraftNote}'
 */
        syncForm.put = (args: { caseDraftNote: string | { id: string } } | [caseDraftNote: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sync.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    sync.form = syncForm
const CaseDraftNoteController = { index, show, sync }

export default CaseDraftNoteController