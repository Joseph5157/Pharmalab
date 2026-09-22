import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../wayfinder';
import syncSpike31d29e from './sync-spike';
import cases from './cases';
/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
export const dashboard = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
});

dashboard.definition = {
    methods: ['get', 'head'],
    url: '/student',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
const dashboardForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
dashboardForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DashboardController::dashboard
 * @see app/Http/Controllers/DashboardController.php:24
 * @route '/student'
 */
dashboardForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: dashboard.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

dashboard.form = dashboardForm;
/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
export const syncSpike = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: syncSpike.url(options),
    method: 'get',
});

syncSpike.definition = {
    methods: ['get', 'head'],
    url: '/student/sync-spike',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
syncSpike.url = (options?: RouteQueryOptions) => {
    return syncSpike.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
syncSpike.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: syncSpike.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
syncSpike.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: syncSpike.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
const syncSpikeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: syncSpike.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
syncSpikeForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: syncSpike.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CaseDraftNoteController::syncSpike
 * @see app/Http/Controllers/CaseDraftNoteController.php:21
 * @route '/student/sync-spike'
 */
syncSpikeForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: syncSpike.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

syncSpike.form = syncSpikeForm;
/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
export const portfolio = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: portfolio.url(options),
    method: 'get',
});

portfolio.definition = {
    methods: ['get', 'head'],
    url: '/student/portfolio',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
portfolio.url = (options?: RouteQueryOptions) => {
    return portfolio.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
portfolio.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: portfolio.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
portfolio.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: portfolio.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
const portfolioForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: portfolio.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
portfolioForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: portfolio.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Student\PortfolioController::portfolio
 * @see app/Http/Controllers/Student/PortfolioController.php:14
 * @route '/student/portfolio'
 */
portfolioForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: portfolio.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

portfolio.form = portfolioForm;
const student = {
    dashboard: Object.assign(dashboard, dashboard),
    syncSpike: Object.assign(syncSpike, syncSpike31d29e),
    cases: Object.assign(cases, cases),
    portfolio: Object.assign(portfolio, portfolio),
};

export default student;
