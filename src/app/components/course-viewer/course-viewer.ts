import { Component, inject, signal, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { LearningService } from '../../services/learning.service';
import { CourseService } from '../../services/course.service';
import { ApiService } from '../../services/api.service';
import { TranslateModule } from '@ngx-translate/core';
import { MarkedPipe } from '../../pipes/marked.pipe';
import { toSignal } from '@angular/core/rxjs-interop';
import { map, switchMap, tap } from 'rxjs/operators';
import { combineLatest } from 'rxjs';
import { TestViewerComponent } from '../test-viewer/test-viewer';
import { CompletionModalComponent } from '../completion-modal/completion-modal';


@Component({
    selector: 'app-course-viewer',
    imports: [CommonModule, TranslateModule, MarkedPipe, TestViewerComponent, CompletionModalComponent],
    templateUrl: './course-viewer.html'
})
export class CourseViewerComponent {
    private route = inject(ActivatedRoute);
    private router = inject(Router);
    private learningService = inject(LearningService);
    private courseService = inject(CourseService);
    private apiService = inject(ApiService);

    courseId = toSignal(this.route.paramMap.pipe(map(params => Number(params.get('courseId')))));

    // Fetch course items (pages and tests)
    pages$ = this.route.paramMap.pipe(
        switchMap(params => this.courseService.getPages().pipe(
            map(pages => pages.filter(p => p.course_id == params.get('courseId')).sort((a, b) => a.display_order - b.display_order))
        ))
    );

    course$ = this.route.paramMap.pipe(
        switchMap(params => this.courseService.getCourses().pipe(
            map(courses => courses.find(c => c.id == Number(params.get('courseId'))))
        ))
    );

    progress$ = this.route.paramMap.pipe(
        switchMap(params => this.learningService.getCourseProgress(Number(params.get('courseId'))))
    );

    selectedPage = signal<any>(null);
    completedPageIds = signal<number[]>([]);
    showCompletionModal = signal<boolean>(false);
    courseTitle = signal<string>('');
    sidebarOpen = signal<boolean>(true);

    constructor() {
        // Set course title
        this.course$.subscribe(course => {
            if (course) {
                this.courseTitle.set(course.title);
            }
        });

        // Combine pages and progress to determine which page to show
        combineLatest([this.pages$, this.progress$]).subscribe(([pages, progress]) => {
            if (pages && pages.length > 0) {
                // Update completed IDs
                if (progress && progress.completed_page_ids) {
                    this.completedPageIds.set(progress.completed_page_ids);
                }

                // Find first incomplete page
                const completedIds = this.completedPageIds();
                const firstIncomplete = pages.find(p => !completedIds.includes(p.id));

                // If we already have a selection, don't override it unless it's invalid
                if (!this.selectedPage()) {
                    if (firstIncomplete) {
                        this.selectPage(firstIncomplete);
                    } else {
                        // If all completed (or none), select the first one
                        this.selectPage(pages[0]);
                    }
                }
            }
        });
    }

    toggleSidebar() {
        this.sidebarOpen.update(v => !v);
    }

    selectPage(page: any) {
        this.selectedPage.set(page);
        // On mobile, auto-close sidebar when selecting a page
        if (window.innerWidth < 640) { // sm breakpoint
            this.sidebarOpen.set(false);
        }
    }

    completeLesson(pageId: number) {
        const cid = this.courseId();
        if (cid) {
            this.learningService.completeLesson(cid, pageId).subscribe(res => {
                console.log('Complete lesson response:', res);
                this.completedPageIds.update(ids => [...ids, pageId]);
                if (res.course_completed) {
                    console.log('Course completed! Showing modal...');
                    this.showCompletionModal.set(true);
                    console.log('Modal state:', this.showCompletionModal());
                } else {
                    // Navigate to next item
                    this.navigateToNextItem(pageId);
                }
            });
        }
    }

    onTestPassed(pageId: number, courseCompleted: boolean = false) {
        console.log('Test passed! pageId:', pageId, 'courseCompleted:', courseCompleted);
        this.completedPageIds.update(ids => [...ids, pageId]);
        if (courseCompleted) {
            console.log('Course completed via test! Showing modal...');
            this.showCompletionModal.set(true);
            console.log('Modal state:', this.showCompletionModal());
        } else {
            // Navigate to next item
            this.navigateToNextItem(pageId);
        }
    }

    navigateToNextItem(currentPageId: number) {
        // Get all pages from the observable
        this.pages$.pipe(
            map(pages => {
                const currentIndex = pages.findIndex(p => p.id === currentPageId);
                if (currentIndex !== -1 && currentIndex < pages.length - 1) {
                    return pages[currentIndex + 1];
                }
                return null;
            })
        ).subscribe(nextPage => {
            if (nextPage) {
                this.selectPage(nextPage);
            }
        });
    }

    closeModal() {
        this.showCompletionModal.set(false);
    }

    navigateToDashboard() {
        this.showCompletionModal.set(false);
        this.router.navigate(['/dashboard']);
    }

    isCompleted(pageId: number) {
        return this.completedPageIds().includes(pageId);
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
        // In a real implementation, we would call the backend to get the signed LTI parameters
        // and then submit a form.
        // For now, we'll simulate it or call a placeholder endpoint.

        console.log('Launching LTI Tool:', toolId);

        // Example flow:
        // this.apiService.getLtiLaunchData(toolId).subscribe(data => {
        //    this.submitLtiForm(data.url, data.params);
        // });

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
