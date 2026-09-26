import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:95
 * @route '/student/cases/{case}/investigations-availability'
 */
export const sync = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/investigations-availability',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:95
 * @route '/student/cases/{case}/investigations-availability'
 */
sync.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return sync.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:95
 * @route '/student/cases/{case}/investigations-availability'
 */
sync.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseInvestigationController::sync
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:95
 * @route '/student/cases/{case}/investigations-availability'
 */
    const syncForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Student/CaseInvestigationController.php:95
 * @route '/student/cases/{case}/investigations-availability'
 */
        syncForm.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sync.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    sync.form = syncForm
const availability = {
    sync: Object.assign(sync, sync),
}

export default availability