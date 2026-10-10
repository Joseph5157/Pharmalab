import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import academic from './academic'
import programmes from './programmes'
import cohorts from './cohorts'
import clinicalSites from './clinical-sites'
import departments from './departments'
import wards from './wards'
import people from './people'
import rotations from './rotations'
import rotationAssignments from './rotation-assignments'
/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/admin',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:104
* @route '/admin'
*/
dashboardForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

dashboard.form = dashboardForm

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
export const progress = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: progress.url(options),
    method: 'get',
})

progress.definition = {
    methods: ["get","head"],
    url: '/admin/progress',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
progress.url = (options?: RouteQueryOptions) => {
    return progress.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
progress.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
progress.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: progress.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
const progressForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
progressForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\ProgressController::progress
* @see app/Http/Controllers/Admin/ProgressController.php:19
* @route '/admin/progress'
*/
progressForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

progress.form = progressForm

const admin = {
    dashboard: Object.assign(dashboard, dashboard),
    academic: Object.assign(academic, academic),
    progress: Object.assign(progress, progress),
    programmes: Object.assign(programmes, programmes),
    cohorts: Object.assign(cohorts, cohorts),
    clinicalSites: Object.assign(clinicalSites, clinicalSites),
    departments: Object.assign(departments, departments),
    wards: Object.assign(wards, wards),
    people: Object.assign(people, people),
    rotations: Object.assign(rotations, rotations),
    rotationAssignments: Object.assign(rotationAssignments, rotationAssignments),
}

export default admin