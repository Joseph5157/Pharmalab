import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import reviews from './reviews'
import rotations from './rotations'
/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/faculty',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
*/
const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
*/
dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:74
* @route '/faculty'
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
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
*/
export const progress = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: progress.url(options),
    method: 'get',
})

progress.definition = {
    methods: ["get","head"],
    url: '/faculty/progress',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
*/
progress.url = (options?: RouteQueryOptions) => {
    return progress.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
*/
progress.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
*/
progress.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: progress.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
*/
const progressForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
*/
progressForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Faculty\ProgressController::progress
* @see app/Http/Controllers/Faculty/ProgressController.php:13
* @route '/faculty/progress'
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

const faculty = {
    dashboard: Object.assign(dashboard, dashboard),
    reviews: Object.assign(reviews, reviews),
    progress: Object.assign(progress, progress),
    rotations: Object.assign(rotations, rotations),
}

export default faculty