import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/faculty/reviews',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Faculty\ReviewController::index
 * @see app/Http/Controllers/Faculty/ReviewController.php:18
 * @route '/faculty/reviews'
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
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
 */
export const show = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/faculty/reviews/{case}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
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
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
 */
show.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
 */
show.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
 */
    const showForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
 */
        showForm.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Faculty\ReviewController::show
 * @see app/Http/Controllers/Faculty/ReviewController.php:34
 * @route '/faculty/reviews/{case}'
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
/**
* @see \App\Http\Controllers\Faculty\ReviewController::approve
 * @see app/Http/Controllers/Faculty/ReviewController.php:54
 * @route '/faculty/reviews/{case}/approve'
 */
export const approve = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/faculty/reviews/{case}/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Faculty\ReviewController::approve
 * @see app/Http/Controllers/Faculty/ReviewController.php:54
 * @route '/faculty/reviews/{case}/approve'
 */
approve.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return approve.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\ReviewController::approve
 * @see app/Http/Controllers/Faculty/ReviewController.php:54
 * @route '/faculty/reviews/{case}/approve'
 */
approve.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Faculty\ReviewController::approve
 * @see app/Http/Controllers/Faculty/ReviewController.php:54
 * @route '/faculty/reviews/{case}/approve'
 */
    const approveForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Faculty\ReviewController::approve
 * @see app/Http/Controllers/Faculty/ReviewController.php:54
 * @route '/faculty/reviews/{case}/approve'
 */
        approveForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, options),
            method: 'post',
        })
    
    approve.form = approveForm
/**
* @see \App\Http\Controllers\Faculty\ReviewController::returnMethod
 * @see app/Http/Controllers/Faculty/ReviewController.php:67
 * @route '/faculty/reviews/{case}/return'
 */
export const returnMethod = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnMethod.url(args, options),
    method: 'post',
})

returnMethod.definition = {
    methods: ["post"],
    url: '/faculty/reviews/{case}/return',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Faculty\ReviewController::returnMethod
 * @see app/Http/Controllers/Faculty/ReviewController.php:67
 * @route '/faculty/reviews/{case}/return'
 */
returnMethod.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return returnMethod.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\ReviewController::returnMethod
 * @see app/Http/Controllers/Faculty/ReviewController.php:67
 * @route '/faculty/reviews/{case}/return'
 */
returnMethod.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Faculty\ReviewController::returnMethod
 * @see app/Http/Controllers/Faculty/ReviewController.php:67
 * @route '/faculty/reviews/{case}/return'
 */
    const returnMethodForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: returnMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Faculty\ReviewController::returnMethod
 * @see app/Http/Controllers/Faculty/ReviewController.php:67
 * @route '/faculty/reviews/{case}/return'
 */
        returnMethodForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: returnMethod.url(args, options),
            method: 'post',
        })
    
    returnMethod.form = returnMethodForm
const reviews = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
approve: Object.assign(approve, approve),
return: Object.assign(returnMethod, returnMethod),
}

export default reviews