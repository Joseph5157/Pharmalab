# Pharmalab Phase 1 Screen and Route Map

## 1. Routing conventions

- Laravel named routes are authoritative.
- Inertia pages follow `Role/Domain/PageName` organization.
- IDs shown in URLs are ULIDs/UUIDs or approved opaque identifiers.
- Every resource route has a policy check.
- Mutations use POST/PATCH/DELETE and redirect to a stable read page.
- Offline synchronization uses a narrow JSON endpoint because it is a background client operation, not an Inertia navigation.

## 2. Shared and authentication

| Screen | Method and route | Inertia page | Access |
|---|---|---|---|
| Sign in | `GET /login` | `Auth/Login` | Guest |
| Sign in action | `POST /login` | — | Guest |
| Forgot/reset password | Laravel auth routes | `Auth/ForgotPassword`, `Auth/ResetPassword` | Guest |
| Notifications | `GET /notifications` | `Shared/Notifications/Index` | Authenticated |
| Mark notification read | `PATCH /notifications/{notification}` | — | Owner |
| Profile | `GET /profile` | `Shared/Profile/Show` | Authenticated |
| Device drafts/help | `GET /offline` | `Shared/Offline/Index` | Authenticated |
| Help | `GET /help` | `Shared/Help/Index` | Authenticated |

## 3. Student routes

| Screen/action | Method and route | Inertia page | Policy/notes |
|---|---|---|---|
| Home | `GET /student` | `Student/Dashboard` | Student role |
| Rotations | `GET /student/rotations` | `Student/Rotations/Index` | Assigned only |
| Rotation detail | `GET /student/rotations/{rotation}` | `Student/Rotations/Show` | Assignment required |
| Cases | `GET /student/cases` | `Student/Cases/Index` | Own cases |
| New case | `GET /student/cases/create` | `Student/Cases/Create` | Active assignment |
| Create case | `POST /student/cases` | — | Active assignment |
| Case overview | `GET /student/cases/{case}` | `Student/Cases/Show` | Owner |
| Edit details | `GET /student/cases/{case}/details` | `Student/Cases/Sections/Details` | Editable state |
| Edit history | `GET /student/cases/{case}/history` | `Student/Cases/Sections/History` | Editable state |
| Edit examination | `GET /student/cases/{case}/examination` | `Student/Cases/Sections/Examination` | Editable state |
| Edit diagnoses | `GET /student/cases/{case}/diagnoses` | `Student/Cases/Sections/Diagnoses` | Editable state |
| Edit labs | `GET /student/cases/{case}/investigations` | `Student/Cases/Sections/Investigations` | Editable state |
| Edit medications | `GET /student/cases/{case}/medications` | `Student/Cases/Sections/Medications` | Editable state |
| Edit SOAP | `GET /student/cases/{case}/soap` | `Student/Cases/Sections/Soap` | Editable state |
| Edit interventions | `GET /student/cases/{case}/interventions` | `Student/Cases/Sections/Interventions` | Editable state |
| Edit ADR | `GET /student/cases/{case}/adr` | `Student/Cases/Sections/Adr` | Feature/template enabled |
| Counselling/monitoring | `GET /student/cases/{case}/plan` | `Student/Cases/Sections/Plan` | Feature/template enabled |
| Save section | `PATCH /student/cases/{case}/sections/{section}` | — | Section allow-list + version |
| Review case | `GET /student/cases/{case}/review` | `Student/Cases/Review` | Owner |
| Submit | `POST /student/cases/{case}/submit` | — | Transition policy |
| Feedback/activity | `GET /student/cases/{case}/activity` | `Student/Cases/Activity` | Owner |
| Resubmit | `POST /student/cases/{case}/resubmit` | — | Transition policy |
| Portfolio | `GET /student/portfolio` | `Student/Portfolio/Index` | Own approved cases |
| Portfolio case | `GET /student/portfolio/{case}` | `Student/Portfolio/Show` | Own approved case |
| Reference | `GET /student/reference` | `Student/Reference/Index` | Student role |

## 4. Faculty routes

| Screen/action | Method and route | Inertia page | Policy/notes |
|---|---|---|---|
| Dashboard | `GET /faculty` | `Faculty/Dashboard` | Faculty role |
| Review queue | `GET /faculty/reviews` | `Faculty/Reviews/Index` | Authorized assignments |
| Case reader | `GET /faculty/reviews/{caseVersion}` | `Faculty/Reviews/Show` | Review permission |
| Start review | `POST /faculty/reviews/{caseVersion}/start` | — | Idempotent transition |
| Add comment | `POST /faculty/reviews/{caseVersion}/comments` | — | Review permission |
| Update comment | `PATCH /faculty/comments/{comment}` | — | Comment author/rules |
| Resolve/reopen comment | `POST /faculty/comments/{comment}/resolve` or `/reopen` | — | Review permission |
| Save rubric | `PUT /faculty/reviews/{caseVersion}/rubric` | — | Review permission |
| Return case | `POST /faculty/reviews/{caseVersion}/return` | — | Transition policy |
| Compare revisions | `GET /faculty/reviews/{caseVersion}/compare` | `Faculty/Reviews/Compare` | Review permission |
| Approve case | `POST /faculty/reviews/{caseVersion}/approve` | — | Transition policy |
| Students | `GET /faculty/students` | `Faculty/Students/Index` | Assigned scope |
| Student progress | `GET /faculty/students/{student}` | `Faculty/Students/Show` | Assigned scope |
| Rotation roster | `GET /faculty/rotations/{rotation}` | `Faculty/Rotations/Show` | Assigned rotation |

## 5. Admin routes

Use standard resource routes where practical.

| Domain | Route prefix | Inertia pages | Notes |
|---|---|---|---|
| Dashboard | `/admin` | `Admin/Dashboard` | Operational overview |
| Users | `/admin/users` | `Admin/Users/*` | Create, edit, deactivate and import |
| Programmes | `/admin/programmes` | `Admin/Programmes/*` | Institution scoped |
| Cohorts | `/admin/cohorts` | `Admin/Cohorts/*` | Institution scoped |
| Clinical sites | `/admin/clinical-sites` | `Admin/ClinicalSites/*` | Includes departments/wards |
| Rotations | `/admin/rotations` | `Admin/Rotations/*` | Draft through archive |
| Assignments | `/admin/rotations/{rotation}/assignments` | `Admin/Assignments/*` | Student/preceptor assignment |
| Case templates | `/admin/case-templates` | `Admin/CaseTemplates/*` | Versioned; P1 |
| Rubrics | `/admin/rubrics` | `Admin/Rubrics/*` | Versioned; P1 |
| Reports | `/admin/reports` | `Admin/Reports/*` | Authorized operational data |
| Audit log | `/admin/audit` | `Admin/Audit/Index` | Read-only |

Import preview and confirmation use separate routes so invalid rows never partially appear without explicit handling.

## 6. Sync endpoints

| Method and route | Purpose |
|---|---|
| `POST /sync/case-sections` | Idempotently apply one allowed section operation. |
| `GET /sync/cases/{case}/status` | Return current server version and safe changed-section metadata. |

Requirements:

- Authenticated session and CSRF protection
- Case/section policy
- Client operation ID
- Base lock version
- Size and rate limits
- Structured success/conflict/error response

## 7. Primary Vue component map

| Area | Components |
|---|---|
| Layout | `AppShell`, `MobileBottomNav`, `DesktopSidebar`, `PageHeader`, `StickyActionBar` |
| Forms | `FormField`, `TextInput`, `TextArea`, `Combobox`, `UnitInput`, `DateTimeField`, `ErrorSummary` |
| Workflow | `CaseTaskList`, `CompletionMeter`, `AutosaveStatus`, `SubmissionChecklist`, `StatusTimeline` |
| Clinical | `CaseIdentityCard`, `VitalGrid`, `LabResultCard`, `MedicationCard`, `SoapEditor`, `InterventionForm`, `AdrForm` |
| Review | `CommentThread`, `CommentComposer`, `RubricMatrix`, `RevisionDiff`, `DecisionPanel` |
| States | `OfflineBanner`, `EmptyState`, `LoadingSkeleton`, `PermissionState`, `ConflictResolver` |

## 8. Navigation shells

### Student mobile

Home, Cases, Add, Reference, Profile.

### Faculty mobile

Home, Review, Students, Notifications, Profile.

### Tablet/desktop

Role-aware sidebar. Faculty case review may use section navigation + content + comments/decision panel, while preserving a single-column fallback.

## 9. Error and state contract

Every P0 page covers:

- Loading
- Empty
- Validation failure with retained input
- Unauthorized/read-only
- Server failure and retry
- Saving/saved
- Offline/local-only
- Conflict/stale version
- Returned or approved state where relevant

Error responses use stable keys so the Vue error summary can link to fields.
