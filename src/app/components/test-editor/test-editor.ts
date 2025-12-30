import { Component, Input, Output, EventEmitter, OnInit, OnChanges, SimpleChanges, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CourseService } from '../../services/course.service';

interface Option {
  id?: number;
  option_text: string;
  is_correct: boolean;
}

interface Question {
  id?: number;
  question_text: string;
  type: 'multiple_choice';
  feedback?: string;
  display_order: number;
  options: Option[];
}

interface Test {
  id?: number;
  page_id: number;
  description: string;
  show_correct_answers?: boolean;
  questions: Question[];
}

@Component({
  selector: 'app-test-editor',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './test-editor.html',
  styleUrls: ['./test-editor.css']
})
export class TestEditorComponent implements OnInit, OnChanges {
  @Input() pageId!: number;
  @Output() saved = new EventEmitter<void>();

  test: Test = {
    page_id: 0,
    description: '',
    show_correct_answers: false,
    questions: []
  };

  loading = false;

  private courseService = inject(CourseService);
  private cdr = inject(ChangeDetectorRef);

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
    this.loading = true;
    this.test = {
      page_id: this.pageId,
      description: '',
      show_correct_answers: false,
      questions: []
    };
    this.cdr.markForCheck();

    this.courseService.getTestByPageId(this.pageId).subscribe({
      next: (data) => {
        if (data) {
          this.test = data;
          // Ensure options are boolean
          this.test.questions.forEach(q => {
            q.options.forEach(o => o.is_correct = !!o.is_correct);
          });
          // Ensure boolean for show_correct_answers (backend sends 0/1)
          this.test.show_correct_answers = !!this.test.show_correct_answers;
        }
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (err) => {
        // 404 is expected if no test created yet
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  addQuestion() {
    this.test.questions.push({
      question_text: '',
      type: 'multiple_choice',
      feedback: '',
      display_order: this.test.questions.length,
      options: [
        { option_text: '', is_correct: false },
        { option_text: '', is_correct: false }
      ]
    });
  }

  removeQuestion(index: number) {
    this.test.questions.splice(index, 1);
  }

  addOption(question: Question) {
    question.options.push({ option_text: '', is_correct: false });
  }

  removeOption(question: Question, index: number) {
    question.options.splice(index, 1);
  }

  save() {
    this.test.page_id = this.pageId;
    this.courseService.saveTest(this.test).subscribe(() => {
      this.saved.emit();
      alert('Test saved!');
    });
  }
}
