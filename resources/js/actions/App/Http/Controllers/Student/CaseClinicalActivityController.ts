import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:22
 * @route '/student/cases/{case}/clinical-activities/adr'
 */
export const syncAdr = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: syncAdr.url(args, options),
    method: 'put',
})

syncAdr.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/clinical-activities/adr',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:22
 * @route '/student/cases/{case}/clinical-activities/adr'
 */
syncAdr.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return syncAdr.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:22
 * @route '/student/cases/{case}/clinical-activities/adr'
 */
syncAdr.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: syncAdr.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:22
 * @route '/student/cases/{case}/clinical-activities/adr'
 */
    const syncAdrForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: syncAdr.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:22
 * @route '/student/cases/{case}/clinical-activities/adr'
 */
        syncAdrForm.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: syncAdr.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    syncAdr.form = syncAdrForm
/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncCounselling
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:46
 * @route '/student/cases/{case}/clinical-activities/counselling'
 */
export const syncCounselling = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: syncCounselling.url(args, options),
    method: 'put',
})

syncCounselling.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/clinical-activities/counselling',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncCounselling
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:46
 * @route '/student/cases/{case}/clinical-activities/counselling'
 */
syncCounselling.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return syncCounselling.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncCounselling
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:46
 * @route '/student/cases/{case}/clinical-activities/counselling'
 */
syncCounselling.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: syncCounselling.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncCounselling
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:46
 * @route '/student/cases/{case}/clinical-activities/counselling'
 */
    const syncCounsellingForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: syncCounselling.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncCounselling
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:46
 * @route '/student/cases/{case}/clinical-activities/counselling'
 */
        syncCounsellingForm.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: syncCounselling.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    syncCounselling.form = syncCounsellingForm
/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::store
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:70
 * @route '/student/cases/{case}/clinical-activities'
 */
export const store = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/student/cases/{case}/clinical-activities',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::store
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:70
 * @route '/student/cases/{case}/clinical-activities'
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
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::store
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:70
 * @route '/student/cases/{case}/clinical-activities'
 */
store.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::store
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:70
 * @route '/student/cases/{case}/clinical-activities'
 */
    const storeForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::store
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:70
 * @route '/student/cases/{case}/clinical-activities'
 */
        storeForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::sync
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:95
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
export const sync = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/clinical-activities/{activity}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::sync
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:95
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
sync.url = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    activity: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                activity: typeof args.activity === 'object'
                ? args.activity.id
                : args.activity,
                }

    return sync.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{activity}', parsedArgs.activity.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::sync
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:95
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
sync.put = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::sync
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:95
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
    const syncForm = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sync.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::sync
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:95
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
        syncForm.put = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::destroy
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:123
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
export const destroy = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/student/cases/{case}/clinical-activities/{activity}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::destroy
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:123
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
destroy.url = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    case: args[0],
                    activity: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        case: typeof args.case === 'object'
                ? args.case.id
                : args.case,
                                activity: typeof args.activity === 'object'
                ? args.activity.id
                : args.activity,
                }

    return destroy.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace('{activity}', parsedArgs.activity.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::destroy
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:123
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
destroy.delete = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::destroy
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:123
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
    const destroyForm = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::destroy
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:123
 * @route '/student/cases/{case}/clinical-activities/{activity}'
 */
        destroyForm.delete = (args: { case: string | { id: string }, activity: string | { id: string } } | [caseParam: string | { id: string }, activity: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const CaseClinicalActivityController = { syncAdr, syncCounselling, store, sync, destroy }

export default CaseClinicalActivityController