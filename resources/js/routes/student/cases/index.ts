import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import soap1a8e66 from './soap'
/**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/student/cases',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Student\CaseController::index
 * @see app/Http/Controllers/Student/CaseController.php:21
 * @route '/student/cases'
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
* @see \App\Http\Controllers\Student\CaseController::store
 * @see app/Http/Controllers/Student/CaseController.php:36
 * @route '/student/cases'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/student/cases',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Student\CaseController::store
 * @see app/Http/Controllers/Student/CaseController.php:36
 * @route '/student/cases'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\CaseController::store
 * @see app/Http/Controllers/Student/CaseController.php:36
 * @route '/student/cases'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Student\CaseController::store
 * @see app/Http/Controllers/Student/CaseController.php:36
 * @route '/student/cases'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\CaseController::store
 * @see app/Http/Controllers/Student/CaseController.php:36
 * @route '/student/cases'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
 */
export const show = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/student/cases/{case}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
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
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
 */
show.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
 */
show.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
 */
    const showForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
 */
        showForm.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Student\CaseController::show
 * @see app/Http/Controllers/Student/CaseController.php:80
 * @route '/student/cases/{case}'
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
 * @see \App\Http\Controllers\Student\CaseEditorController::show
 * @route '/student/cases/{case}/edit'
 */
export const edit = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ['get', 'head'],
    url: '/student/cases/{case}/edit',
} satisfies RouteDefinition<['get', 'head']>

edit.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') args = { case: args }
    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) args = { case: args.id }
    if (Array.isArray(args)) args = { case: args[0] }

    const parsedArgs = { case: typeof args.case === 'object' ? args.case.id : args.case }

    return edit.definition.url.replace('{case}', parsedArgs.case.toString()).replace(/\/+$/, '') + queryParams(options)
}

edit.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const editForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

editForm.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

editForm.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string }] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, { [options?.mergeQuery ? 'mergeQuery' : 'query']: { _method: 'HEAD', ...(options?.query ?? options?.mergeQuery ?? {}) } }),
    method: 'get',
})

edit.form = editForm
/**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
export const soap = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: soap.url(args, options),
    method: 'get',
})

soap.definition = {
    methods: ["get","head"],
    url: '/student/cases/{case}/soap',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
soap.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return soap.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
soap.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: soap.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
soap.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: soap.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
    const soapForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: soap.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
        soapForm.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: soap.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Student\SoapController::soap
 * @see app/Http/Controllers/Student/SoapController.php:18
 * @route '/student/cases/{case}/soap'
 */
        soapForm.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: soap.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    soap.form = soapForm
/**
* @see \App\Http\Controllers\Student\SubmissionController::submit
 * @see app/Http/Controllers/Student/SubmissionController.php:14
 * @route '/student/cases/{case}/submit'
 */
export const submit = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/student/cases/{case}/submit',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Student\SubmissionController::submit
 * @see app/Http/Controllers/Student/SubmissionController.php:14
 * @route '/student/cases/{case}/submit'
 */
submit.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return submit.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\SubmissionController::submit
 * @see app/Http/Controllers/Student/SubmissionController.php:14
 * @route '/student/cases/{case}/submit'
 */
submit.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Student\SubmissionController::submit
 * @see app/Http/Controllers/Student/SubmissionController.php:14
 * @route '/student/cases/{case}/submit'
 */
    const submitForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Student\SubmissionController::submit
 * @see app/Http/Controllers/Student/SubmissionController.php:14
 * @route '/student/cases/{case}/submit'
 */
        submitForm.post = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(args, options),
            method: 'post',
        })
    
    submit.form = submitForm
const cases = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
edit: Object.assign(edit, edit),
soap: Object.assign(soap, soap1a8e66),
submit: Object.assign(submit, submit),
}

export default cases
