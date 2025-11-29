import { Component, inject, signal, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { LearningService } from '../../services/learning.service';
import { CourseService } from '../../services/course.service';
import { TranslateModule } from '@ngx-translate/core';
import { MarkedPipe } from '../../pipes/marked.pipe';
import { toSignal } from '@angular/core/rxjs-interop';
import { map, switchMap, tap } from 'rxjs/operators';
import { combineLatest } from 'rxjs';
import { TestViewerComponent } from '../test-viewer/test-viewer';


@Component({
    selector: 'app-course-viewer',
    imports: [CommonModule, TranslateModule, MarkedPipe, TestViewerComponent],
    templateUrl: './course-viewer.html'
})
export class CourseViewerComponent {
    private route = inject(ActivatedRoute);
    private learningService = inject(LearningService);
    private courseService = inject(CourseService);

    courseId = toSignal(this.route.paramMap.pipe(map(params => Number(params.get('courseId')))));

    // Fetch course items (pages and tests)
    pages$ = this.route.paramMap.pipe(
        switchMap(params => this.courseService.getPages().pipe(
            map(pages => pages.filter(p => p.course_id == params.get('courseId')).sort((a, b) => a.display_order - b.display_order))
        ))
    );

    progress$ = this.route.paramMap.pipe(
        switchMap(params => this.learningService.getCourseProgress(Number(params.get('courseId'))))
    );

    selectedPage = signal<any>(null);
    completedPageIds = signal<number[]>([]);

    constructor() {
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

    selectPage(page: any) {
        this.selectedPage.set(page);
    }

    completeLesson(pageId: number) {
        const cid = this.courseId();
        if (cid) {
            this.learningService.completeLesson(cid, pageId).subscribe(res => {
                this.completedPageIds.update(ids => [...ids, pageId]);
            });
        }
    }

    onTestPassed(pageId: number) {
        this.completedPageIds.update(ids => [...ids, pageId]);
        // Auto-advance to next lesson?
        // For now, just mark complete.
    }

    isCompleted(pageId: number) {
        return this.completedPageIds().includes(pageId);
    }
}
