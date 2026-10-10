import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
export const exportMethod = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/admin/cases/{case}/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
exportMethod.url = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return exportMethod.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
exportMethod.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
exportMethod.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
const exportMethodForm = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
exportMethodForm.get = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\CaseExportController::exportMethod
* @see app/Http/Controllers/Admin/CaseExportController.php:14
* @route '/admin/cases/{case}/export'
*/
exportMethodForm.head = (args: { case: string | { id: string } } | [caseParam: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportMethod.form = exportMethodForm

const CaseExportController = { exportMethod, export: exportMethod }

export default CaseExportController