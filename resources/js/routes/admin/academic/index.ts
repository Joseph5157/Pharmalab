import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../wayfinder';
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
const academic = {
    index: Object.assign(index, index),
};

export default academic;
