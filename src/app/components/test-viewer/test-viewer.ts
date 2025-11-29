import { Component, Input, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CourseService } from '../../services/course.service';

@Component({
  selector: 'app-test-viewer',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './test-viewer.html',
  styleUrls: ['./test-viewer.css']
})
export class TestViewerComponent implements OnInit {
  @Input() testId!: number;

  test = signal<any>(null);
  userAnswers = signal<{ [questionId: number]: number[] }>({}); // questionId -> array of selected option IDs
  submitted = signal<boolean>(false);
  score = signal<number>(0);
  totalQuestions = signal<number>(0);

  constructor(private courseService: CourseService) { }

  ngOnInit() {
    if (this.testId) {
      this.loadTest();
    }
  }

  loadTest() {
    this.courseService.getTest(this.testId).subscribe(data => {
      this.test.set(data);
      this.totalQuestions.set(data.questions.length);
      // Initialize user answers
      const initialAnswers: { [key: number]: number[] } = {};
      data.questions.forEach((q: any) => {
        initialAnswers[q.id] = [];
      });
      this.userAnswers.set(initialAnswers);
    });
  }

  toggleOption(questionId: number, optionId: number, isMultiple: boolean = false) {
    if (this.submitted()) return;

    const currentAnswers = this.userAnswers();
    const questionAnswers = currentAnswers[questionId] || [];

    if (isMultiple) {
      // For now, we only support single choice per question in UI logic usually, 
      // but DB supports multiple correct options. 
      // Let's assume multiple choice means checkboxes.
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

  submit() {
    if (this.submitted()) return;

    let correctCount = 0;
    const test = this.test();
    const answers = this.userAnswers();

    test.questions.forEach((q: any) => {
      const correctOptionIds = q.options.filter((o: any) => o.is_correct).map((o: any) => o.id);
      const userSelectedIds = answers[q.id] || [];

      // Check if arrays match (ignoring order)
      const isCorrect = correctOptionIds.length === userSelectedIds.length &&
        correctOptionIds.every((id: number) => userSelectedIds.includes(id));

      if (isCorrect) {
        correctCount++;
      }
    });

    this.score.set(correctCount);
    this.submitted.set(true);
  }

  retry() {
    this.submitted.set(false);
    this.score.set(0);
    this.loadTest(); // Reload to reset
  }
}
