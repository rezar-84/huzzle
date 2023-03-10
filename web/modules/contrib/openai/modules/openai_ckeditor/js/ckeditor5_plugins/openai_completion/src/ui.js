/**
 * @file registers the OpenAI Completion button and binds functionality to it.
 */

import {Plugin} from 'ckeditor5/src/core';
import {ButtonView, ContextualBalloon, clickOutsideHandler} from 'ckeditor5/src/ui';
import icon from '../../../../icons/completion.svg';
import FormView from './form';

export default class Ui extends Plugin {
  static get requires() {
    return [ ContextualBalloon ];
  }

  init() {
    const editor = this.editor;

    // Create the balloon and the form view.
    this._balloon = this.editor.plugins.get( ContextualBalloon );
    this.formView = this._createFormView();

    // This will register the completion toolbar button.
    editor.ui.componentFactory.add('completion', (locale) => {
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('OpenAI Completion'),
        icon,
        tooltip: true,
      });

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () =>
        this._showUI(),
      );

      return buttonView;
    });

    // @todo Could this be configurable in Drupal?
    // @todo how can we prevent browser shortcut collision?
    editor.keystrokes.set( 'Ctrl+M', ( event, cancel )=> {
      this._showUI();
    }, { priority: 'high' } );
  }

  _createFormView() {
    const editor = this.editor;
    const formView = new FormView(editor.locale);

    this.listenTo( formView, 'submit', () => {
      const prompt = formView.promptInputView.fieldView.element.value;

      // @todo Need to have an AJAX indicator while the API waits for a response.
      // @todo add error handling

      editor.model.change( writer => {
        fetch(drupalSettings.path.baseUrl + 'api/openai-ckeditor/generate-completion', {
          method: 'POST',
          credentials: 'same-origin',
          body: JSON.stringify({'prompt': prompt}),
        })
          .then((response) => response.json())
          .then((answer) => editor.model.insertContent(
            writer.createText(answer.text)
          ))
          .then(() => this._hideUI()
        )
      } );
    } );

    // Hide the form view after clicking the "Cancel" button.
    this.listenTo(formView, 'cancel', () => {
      this._hideUI();
    } );

    // Hide the form view when clicking outside the balloon.
    clickOutsideHandler( {
      emitter: formView,
      activator: () => this._balloon.visibleView === formView,
      contextElements: [ this._balloon.view.element ],
      callback: () => this._hideUI()
    } );

    return formView;
  }

  _getBalloonPositionData() {
    const view = this.editor.editing.view;
    const viewDocument = view.document;
    let target = null;

    // Set a target position by converting view selection range to DOM.
    target = () => view.domConverter.viewRangeToDom(
      viewDocument.selection.getFirstRange()
    );

    return {
      target
    };
  }

  _showUI() {
    this._balloon.add( {
      view: this.formView,
      position: this._getBalloonPositionData()
    } );

    this.formView.focus();
  }

  _hideUI() {
    this.formView.promptInputView.fieldView.value = '';
    this.formView.element.reset();
    this._balloon.remove( this.formView );
    this.editor.editing.view.focus();
  }
}
