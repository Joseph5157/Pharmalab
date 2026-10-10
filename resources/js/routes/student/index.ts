import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import cases from './cases'
/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/student',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
*/
const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
*/
dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::dashboard
* @see app/Http/Controllers/DashboardController.php:28
* @route '/student'
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
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
export const portfolio = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: portfolio.url(options),
    method: 'get',
})

portfolio.definition = {
    methods: ["get","head"],
    url: '/student/portfolio',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
portfolio.url = (options?: RouteQueryOptions) => {
    return portfolio.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
portfolio.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: portfolio.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
portfolio.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: portfolio.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
const portfolioForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: portfolio.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
portfolioForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: portfolio.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Student\PortfolioController::portfolio
* @see app/Http/Controllers/Student/PortfolioController.php:14
* @route '/student/portfolio'
*/
portfolioForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: portfolio.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

portfolio.form = portfolioForm

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
*/
export const progress = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: progress.url(options),
    method: 'get',
})

progress.definition = {
    methods: ["get","head"],
    url: '/student/progress',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
*/
progress.url = (options?: RouteQueryOptions) => {
    return progress.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
*/
progress.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
*/
progress.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: progress.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
*/
const progressForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
*/
progressForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: progress.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Student\ProgressController::progress
* @see app/Http/Controllers/Student/ProgressController.php:13
* @route '/student/progress'
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

const student = {
    dashboard: Object.assign(dashboard, dashboard),
    cases: Object.assign(cases, cases),
    portfolio: Object.assign(portfolio, portfolio),
    progress: Object.assign(progress, progress),
}

export default student