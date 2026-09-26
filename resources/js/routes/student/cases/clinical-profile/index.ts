import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Student\CaseClinicalProfileController::sync
 * @see app/Http/Controllers/Student/CaseClinicalProfileController.php:14
 * @route '/student/cases/{case}/clinical-profile'
 */
export const sync = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

sync.definition = {
    methods: ["put"],
    url: '/student/cases/{case}/clinical-profile',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Student\CaseClinicalProfileController::sync
 * @see app/Http/Controllers/Student/CaseClinicalProfileController.php:14
 * @route '/student/cases/{case}/clinical-profile'
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
* @see \App\Http\Controllers\Student\CaseClinicalProfileController::sync
 * @see app/Http/Controllers/Student/CaseClinicalProfileController.php:14
 * @route '/student/cases/{case}/clinical-profile'
 */
sync.put = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: sync.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Student\CaseClinicalProfileController::sync
 * @see app/Http/Controllers/Student/CaseClinicalProfileController.php:14
 * @route '/student/cases/{case}/clinical-profile'
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
* @see \App\Http\Controllers\Student\CaseClinicalProfileController::sync
 * @see app/Http/Controllers/Student/CaseClinicalProfileController.php:14
 * @route '/student/cases/{case}/clinical-profile'
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
const clinicalProfile = {
    sync: Object.assign(sync, sync),
}

export default clinicalProfile
