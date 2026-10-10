import ReviewController from './ReviewController'
import RotationTargetController from './RotationTargetController'

const Faculty = {
    ReviewController: Object.assign(ReviewController, ReviewController),
    RotationTargetController: Object.assign(RotationTargetController, RotationTargetController),
}

export default Faculty