import { Component, Input, Output, EventEmitter, OnInit, OnChanges, SimpleChanges, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CourseService } from '../../services/course.service';

import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-test-viewer',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslateModule],
  templateUrl: './test-viewer.html',
  styleUrls: ['./test-viewer.css']
})
export class TestViewerComponent implements OnInit, OnChanges {
  @Input() pageId!: number;
  @Output() passed = new EventEmitter<{ pageId: number, courseCompleted: boolean }>();
  @Output() navigateNext = new EventEmitter<void>();

  test = signal<any>(null);
  userAnswers = signal<{ [questionId: number]: number[] }>({}); // questionId -> array of selected option IDs
  submitted = signal<boolean>(false);
  score = signal<number>(0);
  totalQuestions = signal<number>(0);
  passedStatus = signal<boolean>(false);
  resultDetails = signal<{ [questionId: number]: { correct_options: number[], feedback: string } } | null>(null);
  loading = signal<boolean>(false);
  error = signal<string>('');

  constructor(private courseService: CourseService) { }

  ngOnInit() {
    if (this.pageId) {
      this.loadTest();
    }
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['pageId'] && !changes['pageId'].firstChange) {
      this.loadTest();
    }
  }

  loadTest() {
    this.loading.set(true);
    this.error.set('');
    this.test.set(null);
    this.submitted.set(false);
    this.resultDetails.set(null); // Reset details

    this.courseService.getTestByPageId(this.pageId).subscribe({
      next: (data) => {
        this.test.set(data);
        this.totalQuestions.set(data.questions.length);
        // Initialize user answers
        const initialAnswers: { [key: number]: number[] } = {};
        data.questions.forEach((q: any) => {
          initialAnswers[q.id] = [];
        });
        this.userAnswers.set(initialAnswers);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set('Test not found or could not be loaded.');
        this.loading.set(false);
      }
    });
  }

  toggleOption(questionId: number, optionId: number, isMultiple: boolean = false) {
    if (this.submitted()) return;

    const currentAnswers = this.userAnswers();
    const questionAnswers = currentAnswers[questionId] || [];

    if (isMultiple) {
      const index = questionAnswers.indexOf(optionId);
      if (index > -1) {
        questionAnswers.splice(index, 1);
      } else {
        questionAnswers.push(optionId);
      }
    } else {
      // Radio button behavior
      currentAnswers[questionId] = [optionId];
    }

    // Trigger signal update
    this.userAnswers.set({ ...currentAnswers });
  }

  isMultipleChoice(question: any): boolean {
    return !!question.is_multiple;
  }

  submit() {
    if (this.submitted()) return;

    const test = this.test();
    if (!test) return;

    this.courseService.submitTest(test.id, this.userAnswers()).subscribe({
      next: (result) => {
        this.score.set(result.score);
        this.passedStatus.set(result.passed);
        this.submitted.set(true);

        if (result.details) {
          this.resultDetails.set(result.details);
        }

        if (result.passed) {
          this.passed.emit({
            pageId: this.pageId,
            courseCompleted: result.course_completed || false
          });
        }
      },
      error: (err) => {
        alert('Error submitting test');
      }
    });
  }

  retry() {
    this.submitted.set(false);
    this.score.set(0);
    this.passedStatus.set(false);
    this.loadTest(); // Reload to reset
  }
}
