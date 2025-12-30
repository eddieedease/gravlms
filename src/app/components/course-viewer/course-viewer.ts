import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { CourseService } from '../../services/course.service';
import { MarkedPipe } from '../../pipes/marked.pipe';
import { TranslateModule } from '@ngx-translate/core';
import { map, switchMap, tap } from 'rxjs/operators';
import { Observable } from 'rxjs';
import { TestViewerComponent } from '../test-viewer/test-viewer';
import { CompletionModalComponent } from '../completion-modal/completion-modal';
import { AuthService } from '../../services/auth.service';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { ApiService } from '../../services/api.service';
import { FormsModule } from '@angular/forms';
import { OrganisationService } from '../../services/organisation.service';
import { LearningService } from '../../services/learning.service';

@Component({
    selector: 'app-course-viewer',
    standalone: true,
    imports: [CommonModule, MarkedPipe, TranslateModule, TestViewerComponent, CompletionModalComponent, FormsModule],
    templateUrl: './course-viewer.html',
    styleUrls: ['./course-viewer.scss']
})
export class CourseViewerComponent implements OnInit {
    selectedPage = signal<any>(null);
    pages$!: Observable<any[]>;
    courseId!: number;
    sidebarOpen = signal(true);
    showCompletionModal = signal(false);
    courseTitle = signal('');

    // Progress & Completion
    completedPageIds = signal<number[]>([]);
    courseCompleted = signal(false);

    // LTI Logic
    isLtiMode = signal(false);

    // Assessment Logic
    assessmentInstructions = signal('');
    assessmentStatus = signal<string | null>(null);
    assessmentFeedback = signal<string | null>(null);
    submissionText = '';
    submissionFile: File | null = null;
    isSubmitting = signal(false);

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private courseService: CourseService,
        private authService: AuthService,
        private sanitizer: DomSanitizer,
        private apiService: ApiService,
        private orgService: OrganisationService,
        private learningService: LearningService
    ) {
        this.isLtiMode.set(this.authService.isLtiUser());
    }

    // LTI Iframe Logic
    isLtiCourse = signal(false);
    ltiLaunchUrl = signal('');
    ltiLaunchParams = signal<{ [key: string]: string }>({});

    ngOnInit() {
        // Try to get courseId from param 'courseId' first, then 'id'
        const paramId = this.route.snapshot.paramMap.get('courseId') || this.route.snapshot.paramMap.get('id');
        this.courseId = paramId ? +paramId : 0;

        // Load Course Details first to check if it's LTI
        this.courseService.getCourse(this.courseId).subscribe(course => {
            this.courseTitle.set(course.title);

            if (course.is_lti && course.lti_tool_id) {
                this.isLtiCourse.set(true);
                this.sidebarOpen.set(false); // Hide sidebar for LTI
                this.prepareLtiLaunch(course.lti_tool_id);
            } else {
                // Regular course loading
                this.loadRegularCoursePages();
            }
        });

        this.loadProgress();
    }

    loadRegularCoursePages() {
        this.pages$ = this.courseService.getCoursePages(this.courseId).pipe(
            tap(pages => {
                if (pages.length > 0 && !this.selectedPage()) {
                    // Check for query param pageId
                    const pageIdStr = this.route.snapshot.queryParamMap.get('pageId');
                    if (pageIdStr) {
                        const p = pages.find(page => page.id === +pageIdStr);
                        if (p) this.selectPage(p);
                        else this.selectPage(pages[0]);
                    } else {
                        this.selectPage(pages[0]);
                    }
                }
            })
        );
    }

    prepareLtiLaunch(toolId: number) {
        this.apiService.getLtiConsumerLaunchParams(toolId, this.courseId).subscribe({
            next: (res) => {
                this.ltiLaunchUrl.set(res.url);
                this.ltiLaunchParams.set(res.params || {});

                // Auto-submit the form after a brief delay to ensure DOM is ready
                setTimeout(() => {
                    const form = document.getElementById('lti-launch-form') as HTMLFormElement;
                    if (form) {
                        form.submit();
                    }
                }, 500);
            },
            error: (err) => console.error('LTI Params Error', err)
        });
    }

    // ... existing methods ...

    loadProgress() {
        this.learningService.getCourseProgress(this.courseId).subscribe(progress => {
            if (progress.completed_page_ids) {
                this.completedPageIds.set(progress.completed_page_ids);
            }
            this.courseCompleted.set(progress.is_completed);
        });
    }

    isCompleted(pageId: number): boolean {
        return this.completedPageIds().includes(pageId);
    }

    toggleSidebar() {
        this.sidebarOpen.update(v => !v);
    }

    selectPage(page: any) {
        this.selectedPage.set(page);

        // Only close sidebar on mobile (sm breakpoint is 640px)
        if (window.innerWidth < 640) {
            this.sidebarOpen.set(false);
        }

        // Reset assessment state
        this.assessmentInstructions.set('');
        this.assessmentStatus.set(null);
        this.assessmentFeedback.set(null);
        this.submissionText = '';
        this.submissionFile = null;

        if (page.type === 'assessment') {
            this.loadAssessmentDetails(page.id);
        }
    }

    loadAssessmentDetails(pageId: number) {
        this.apiService.getAssessmentForPage(pageId).subscribe({
            next: (res) => {
                if (res.assessment) {
                    this.assessmentInstructions.set(res.assessment.instructions);
                }
                if (res.submission) {
                    this.assessmentStatus.set(res.submission.status);
                    this.assessmentFeedback.set(res.submission.feedback);
                    this.submissionText = res.submission.submission_text || '';
                }
            },
            error: (err) => console.error(err)
        });
    }

    onAssessmentFileSelected(event: any) {
        const file = event.target.files[0];
        if (file) {
            this.submissionFile = file;
        }
    }

    submitAssessment() {
        if (!this.selectedPage()) return;

        this.isSubmitting.set(true);

        const submitData = (fileUrl: string | null) => {
            this.apiService.submitAssessment(this.selectedPage().id, this.submissionText, fileUrl).subscribe({
                next: () => {
                    this.isSubmitting.set(false);
                    this.assessmentStatus.set('pending');
                    alert('Assessment submitted for grading!');
                },
                error: (err) => {
                    this.isSubmitting.set(false);
                    alert('Failed to submit assessment: ' + (err.error?.error || 'Unknown error'));
                }
            });
        };

        if (this.submissionFile) {
            this.apiService.uploadFile(this.submissionFile, 'assignment', this.courseId).subscribe({
                next: (res) => {
                    submitData(res.url);
                },
                error: () => {
                    this.isSubmitting.set(false);
                    alert('Failed to upload file');
                }
            });
        } else {
            submitData(null);
        }
    }

    getSafeHtml(content: string): SafeHtml {
        return this.sanitizer.bypassSecurityTrustHtml(content);
    }

    completeLesson(pageId: number) {
        if (this.courseId) {
            this.learningService.completeLesson(this.courseId, pageId).subscribe(res => {
                this.completedPageIds.update(ids => [...ids, pageId]);
                if (res.course_completed) {
                    this.showCompletionModal.set(true);
                } else {
                    this.navigateToNextItem(pageId);
                }
            });
        }
    }

    onTestPassed(pageId: number, courseCompleted: boolean = false) {
        this.completedPageIds.update(ids => [...ids, pageId]);
        if (courseCompleted) {
            this.showCompletionModal.set(true);
        }
    }

    navigateToNextItem(currentPageId: number) {
        this.pages$.subscribe(pages => {
            const currentIndex = pages.findIndex(p => p.id === currentPageId);
            if (currentIndex !== -1 && currentIndex < pages.length - 1) {
                this.selectPage(pages[currentIndex + 1]);
            }
        });
    }

    closeModal() {
        this.showCompletionModal.set(false);
    }

    navigateToDashboard() {
        this.showCompletionModal.set(false);
        // Don't allow navigation to dashboard if in LTI mode
        if (!this.isLtiMode()) {
            this.router.navigate(['/dashboard']);
        }
    }

    handleContentClick(event: MouseEvent) {
        const target = event.target as HTMLElement;
        if (target.classList.contains('lti-launch-btn')) {
            const toolId = target.getAttribute('data-tool-id');
            if (toolId) {
                this.launchLtiTool(Number(toolId));
            }
        }
    }

    launchLtiTool(toolId: number) {
        console.log('Launching LTI Tool:', toolId);
        alert('LTI Launch triggered for Tool ID: ' + toolId + '\n(Backend implementation pending)');
    }

    getPreviousPage(pages: any[], currentPage: any): any | null {
        if (!pages || !currentPage) return null;
        const index = pages.findIndex(p => p.id === currentPage.id);
        return index > 0 ? pages[index - 1] : null;
    }

    getNextPage(pages: any[], currentPage: any): any | null {
        if (!pages || !currentPage) return null;
        const index = pages.findIndex(p => p.id === currentPage.id);
        return index !== -1 && index < pages.length - 1 ? pages[index + 1] : null;
    }
}
