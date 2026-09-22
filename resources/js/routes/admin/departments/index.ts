import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/departments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClinicalSiteController::store
 * @see app/Http/Controllers/Admin/ClinicalSiteController.php:54
 * @route '/admin/departments'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const departments = {
    store: Object.assign(store, store),
}

export default departments