import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import availability from './availability'
/**
* @see \App\Http\Controllers\Student\CaseMedicationController::store
 * @see app/Http/Controllers/Student/CaseMedicationController.php:19
 * @route '/student/cases/{case}/medications'
 */
export const store = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/student/cases/{case}/medications',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Student\CaseMedicationController::store
 * @see app/Http/Controllers/Student/CaseMedicationController.php:19
 * @route '/student/cases/{case}/medications'
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
* @see \App\Http\Controllers\Student\CaseMedicationController::store
 * @see app/Http/Controllers/Student/CaseMedicationController.php:19
 * @route '/student/cases/{case}/medications'
 */
store.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Student\CaseMedicationController::store
 * @see app/Http/Controllers/Student/CaseMedicationController.php:19
 * @route '/student/cases/{case}/medications'
 */
    const storeForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseMedicationController::store
 * @see app/Http/Controllers/Student/CaseMedicationController.php:19
 * @route '/student/cases/{case}/medications'
 */
        storeForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Student\CaseMedicationController::sync
 * @see app/Http/Controllers/Student/CaseMedicationController.php:53
 * @route '/student/cases/{case}/medications/{medication}'
 */
export const sync = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/medications/{medication}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseMedicationController::sync
 * @see app/Http/Controllers/Student/CaseMedicationController.php:53
 * @route '/student/cases/{case}/medications/{medication}'
 */
sync.url = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    medication: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                medication: typeof args.medication === 'object'
                ? args.medication.id
                : args.medication,
                }

    return sync.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{medication}', parsedArgs.medication.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseMedicationController::sync
 * @see app/Http/Controllers/Student/CaseMedicationController.php:53
 * @route '/student/cases/{case}/medications/{medication}'
 */
sync.put = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseMedicationController::sync
 * @see app/Http/Controllers/Student/CaseMedicationController.php:53
 * @route '/student/cases/{case}/medications/{medication}'
 */
    const syncForm = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sync.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseMedicationController::sync
 * @see app/Http/Controllers/Student/CaseMedicationController.php:53
 * @route '/student/cases/{case}/medications/{medication}'
 */
        syncForm.put = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Student\CaseMedicationController::destroy
 * @see app/Http/Controllers/Student/CaseMedicationController.php:76
 * @route '/student/cases/{case}/medications/{medication}'
 */
export const destroy = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/student/cases/{case}/medications/{medication}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Student\CaseMedicationController::destroy
 * @see app/Http/Controllers/Student/CaseMedicationController.php:76
 * @route '/student/cases/{case}/medications/{medication}'
 */
destroy.url = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    medication: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                medication: typeof args.medication === 'object'
                ? args.medication.id
                : args.medication,
                }

    return destroy.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{medication}', parsedArgs.medication.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseMedicationController::destroy
 * @see app/Http/Controllers/Student/CaseMedicationController.php:76
 * @route '/student/cases/{case}/medications/{medication}'
 */
destroy.delete = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Student\CaseMedicationController::destroy
 * @see app/Http/Controllers/Student/CaseMedicationController.php:76
 * @route '/student/cases/{case}/medications/{medication}'
 */
    const destroyForm = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseMedicationController::destroy
 * @see app/Http/Controllers/Student/CaseMedicationController.php:76
 * @route '/student/cases/{case}/medications/{medication}'
 */
        destroyForm.delete = (args: { case: string | { id: string }, medication: string | { id: string } } | [caseParam: string | { id: string }, medication: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const medications = {
    store: Object.assign(store, store),
sync: Object.assign(sync, sync),
destroy: Object.assign(destroy, destroy),
availability: Object.assign(availability, availability),
}

export default medications