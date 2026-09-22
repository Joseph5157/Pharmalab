import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/programmes',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:29
 * @route '/admin/programmes'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
const programmes = {
    store: Object.assign(store, store),
};

export default programmes;
