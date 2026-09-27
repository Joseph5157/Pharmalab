import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
export const show = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/student/cases/{case}/submission-review',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
show.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
show.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
show.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
    const showForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
        showForm.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Student\SubmissionReviewController::show
 * @see app/Http/Controllers/Student/SubmissionReviewController.php:16
 * @route '/student/cases/{case}/submission-review'
 */
        showForm.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const SubmissionReviewController = { show }

export default SubmissionReviewController