import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:16
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
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:16
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
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:16
 * @route '/student/cases/{case}/clinical-activities/adr'
 */
syncAdr.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: syncAdr.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalActivityController::syncAdr
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:16
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
 * @see app/Http/Controllers/Student/CaseClinicalActivityController.php:16
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
const CaseClinicalActivityController = { syncAdr }

export default CaseClinicalActivityController