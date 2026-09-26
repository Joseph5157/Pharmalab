import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import availability from './availability'
/**
* @see \App\Http\Controllers\Student\CaseVitalController::store
 * @see app/Http/Controllers/Student/CaseVitalController.php:19
 * @route '/student/cases/{case}/vitals'
 */
export const store = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/student/cases/{case}/vitals',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Student\CaseVitalController::store
 * @see app/Http/Controllers/Student/CaseVitalController.php:19
 * @route '/student/cases/{case}/vitals'
 */
store.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { case: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { case: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                }

    return store.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseVitalController::store
 * @see app/Http/Controllers/Student/CaseVitalController.php:19
 * @route '/student/cases/{case}/vitals'
 */
store.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Student\CaseVitalController::store
 * @see app/Http/Controllers/Student/CaseVitalController.php:19
 * @route '/student/cases/{case}/vitals'
 */
    const storeForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseVitalController::store
 * @see app/Http/Controllers/Student/CaseVitalController.php:19
 * @route '/student/cases/{case}/vitals'
 */
        storeForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Student\CaseVitalController::sync
 * @see app/Http/Controllers/Student/CaseVitalController.php:50
 * @route '/student/cases/{case}/vitals/{vital}'
 */
export const sync = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/vitals/{vital}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseVitalController::sync
 * @see app/Http/Controllers/Student/CaseVitalController.php:50
 * @route '/student/cases/{case}/vitals/{vital}'
 */
sync.url = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    vital: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                vital: typeof args.vital === 'object'
                ? args.vital.id
                : args.vital,
                }

    return sync.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{vital}', parsedArgs.vital.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseVitalController::sync
 * @see app/Http/Controllers/Student/CaseVitalController.php:50
 * @route '/student/cases/{case}/vitals/{vital}'
 */
sync.put = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseVitalController::sync
 * @see app/Http/Controllers/Student/CaseVitalController.php:50
 * @route '/student/cases/{case}/vitals/{vital}'
 */
    const syncForm = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sync.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseVitalController::sync
 * @see app/Http/Controllers/Student/CaseVitalController.php:50
 * @route '/student/cases/{case}/vitals/{vital}'
 */
        syncForm.put = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sync.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    sync.form = syncForm
/**
* @see \App\Http\Controllers\Student\CaseVitalController::destroy
 * @see app/Http/Controllers/Student/CaseVitalController.php:70
 * @route '/student/cases/{case}/vitals/{vital}'
 */
export const destroy = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/student/cases/{case}/vitals/{vital}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Student\CaseVitalController::destroy
 * @see app/Http/Controllers/Student/CaseVitalController.php:70
 * @route '/student/cases/{case}/vitals/{vital}'
 */
destroy.url = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    vital: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                vital: typeof args.vital === 'object'
                ? args.vital.id
                : args.vital,
                }

    return destroy.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{vital}', parsedArgs.vital.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseVitalController::destroy
 * @see app/Http/Controllers/Student/CaseVitalController.php:70
 * @route '/student/cases/{case}/vitals/{vital}'
 */
destroy.delete = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Student\CaseVitalController::destroy
 * @see app/Http/Controllers/Student/CaseVitalController.php:70
 * @route '/student/cases/{case}/vitals/{vital}'
 */
    const destroyForm = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseVitalController::destroy
 * @see app/Http/Controllers/Student/CaseVitalController.php:70
 * @route '/student/cases/{case}/vitals/{vital}'
 */
        destroyForm.delete = (args: { case: string | { id: string }, vital: string | { id: string } } | [caseParam: string | { id: string }, vital: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    destroy.form = destroyForm
const vitals = {
    store: Object.assign(store, store),
sync: Object.assign(sync, sync),
destroy: Object.assign(destroy, destroy),
availability: Object.assign(availability, availability),
}

export default vitals