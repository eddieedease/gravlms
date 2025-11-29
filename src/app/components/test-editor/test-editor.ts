import { Component, Input, Output, EventEmitter, OnInit } from '@angular/core';
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
  display_order: number;
  options: Option[];
}

interface Test {
  id?: number;
  course_id: number;
  title: string;
  description: string;
  display_order: number;
  questions: Question[];
}

@Component({
  selector: 'app-test-editor',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './test-editor.html',
  styleUrls: ['./test-editor.css']
})
export class TestEditorComponent implements OnInit {
  @Input() courseId!: number;
  @Input() testId: number | null = null;
  @Output() close = new EventEmitter<void>();
  @Output() saved = new EventEmitter<void>();

  test: Test = {
    course_id: 0,
    title: '',
    description: '',
    display_order: 0,
    questions: []
  };

  constructor(private courseService: CourseService) { }

  ngOnInit() {
    if (this.testId) {
      this.courseService.getTest(this.testId).subscribe(data => {
        this.test = data;
        // Ensure options are boolean for checkbox binding
        this.test.questions.forEach(q => {
          q.options.forEach(o => o.is_correct = !!o.is_correct);
        });
      });
    } else {
      this.test.course_id = this.courseId;
    }
  }

  addQuestion() {
    this.test.questions.push({
      question_text: '',
      type: 'multiple_choice',
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
    if (this.testId) {
      this.courseService.updateTest(this.testId, this.test).subscribe(() => {
        this.saved.emit();
        this.close.emit();
      });
    } else {
      this.courseService.createTest(this.test).subscribe(() => {
        this.saved.emit();
        this.close.emit();
      });
    }
  }

  cancel() {
    this.close.emit();
  }
}
