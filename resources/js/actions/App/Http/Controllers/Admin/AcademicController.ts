import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/academic',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\AcademicController::index
 * @see app/Http/Controllers/Admin/AcademicController.php:20
 * @route '/admin/academic'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;
/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeProgramme
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
export const storeProgramme = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeProgramme.url(options),
    method: 'post',
});

storeProgramme.definition = {
    methods: ['post'],
    url: '/admin/programmes',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeProgramme
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
storeProgramme.url = (options?: RouteQueryOptions) => {
    return storeProgramme.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeProgramme
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
storeProgramme.post = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeProgramme.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeProgramme
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
const storeProgrammeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeProgramme.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeProgramme
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
storeProgrammeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeProgramme.url(options),
    method: 'post',
});

storeProgramme.form = storeProgrammeForm;
/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeCohort
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
export const storeCohort = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeCohort.url(options),
    method: 'post',
});

storeCohort.definition = {
    methods: ['post'],
    url: '/admin/cohorts',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeCohort
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
storeCohort.url = (options?: RouteQueryOptions) => {
    return storeCohort.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeCohort
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
storeCohort.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeCohort.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeCohort
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
const storeCohortForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeCohort.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::storeCohort
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
storeCohortForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeCohort.url(options),
    method: 'post',
});

storeCohort.form = storeCohortForm;
const AcademicController = { index, storeProgramme, storeCohort };

export default AcademicController;
