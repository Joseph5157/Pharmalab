import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/cohorts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\AcademicController::store
 * @see app/Http/Controllers/Admin/AcademicController.php:53
 * @route '/admin/cohorts'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const cohorts = {
    store: Object.assign(store, store),
}

export default cohorts