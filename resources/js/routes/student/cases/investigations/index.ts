import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import availability from './availability'
/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::store
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:19
 * @route '/student/cases/{case}/investigations'
 */
export const store = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/student/cases/{case}/investigations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::store
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:19
 * @route '/student/cases/{case}/investigations'
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
* @see \App\Http\Controllers\Student\CaseInvestigationController::store
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:19
 * @route '/student/cases/{case}/investigations'
 */
store.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::store
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:19
 * @route '/student/cases/{case}/investigations'
 */
    const storeForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::store
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:19
 * @route '/student/cases/{case}/investigations'
 */
        storeForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:53
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
export const sync = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/investigations/{investigation}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:53
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
sync.url = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    investigation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                investigation: typeof args.investigation === 'object'
                ? args.investigation.id
                : args.investigation,
                }

    return sync.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{investigation}', parsedArgs.investigation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:53
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
sync.put = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:53
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
    const syncForm = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sync.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:53
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
        syncForm.put = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Student\CaseInvestigationController::destroy
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:76
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
export const destroy = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/student/cases/{case}/investigations/{investigation}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::destroy
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:76
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
destroy.url = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    investigation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                investigation: typeof args.investigation === 'object'
                ? args.investigation.id
                : args.investigation,
                }

    return destroy.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{investigation}', parsedArgs.investigation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::destroy
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:76
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
destroy.delete = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::destroy
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:76
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
    const destroyForm = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::destroy
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:76
 * @route '/student/cases/{case}/investigations/{investigation}'
 */
        destroyForm.delete = (args: { case: string | { id: string }, investigation: string | { id: string } } | [caseParam: string | { id: string }, investigation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    destroy.form = destroyForm
const investigations = {
    store: Object.assign(store, store),
sync: Object.assign(sync, sync),
destroy: Object.assign(destroy, destroy),
availability: Object.assign(availability, availability),
}

export default investigations