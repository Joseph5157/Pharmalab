import ReviewController from './ReviewController'
import ProgressController from './ProgressController'
import RotationTargetController from './RotationTargetController'

const Faculty = {
    ReviewController: Object.assign(ReviewController, ReviewController),
    ProgressController: Object.assign(ProgressController, ProgressController),
    RotationTargetController: Object.assign(RotationTargetController, RotationTargetController),
}

export default Faculty