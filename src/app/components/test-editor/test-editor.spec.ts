import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TestEditor } from './test-editor';

describe('TestEditor', () => {
  let component: TestEditor;
  let fixture: ComponentFixture<TestEditor>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TestEditor]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TestEditor);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
